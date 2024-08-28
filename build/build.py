import os
import sys
import subprocess
import json
import argparse
import datetime
import re
import smtplib
import email
import logging

def send_email(receivers, version, impacted_tables):
    sender = 'noreply@ericsson.com'
    message_template = """From: DDP Build <noreply@ericsson.com>
To: {0}
Subject: DDP Schema Change

The following tables have been modified in DDP {1}

{2}
"""
    message = message_template.format(", ".join(receivers), version, ", ".join(impacted_tables))

    try:
        logging.debug("send_email: Creating smtpObj")
        smtpObj = smtplib.SMTP(timeout=30)
        smtpObj.set_debuglevel(2)
        logging.debug("send_email: connecting")
        smtpObj.connect('smtp-central.internal.ericsson.com', 25)
        smtpObj.starttls()
        logging.debug("send_email: sending")
        smtpObj.sendmail(sender, receivers, message)
        logging.debug("send_email: done")
        smtpObj.quit()
    except SMTPException:
        logging.exception("Failed to send email")

def notify_external(version, schema_changes):
    if 'statsdb' not in schema_changes:
        return

    statsdb_schema_changes = schema_changes['statsdb']['summary']
    logging.debug("notify_external: statsdb_schema_changes=%s", statsdb_schema_changes)

    changed_tables = []
    for change_list in statsdb_schema_changes.values():
        for table in change_list:
            if table not in changed_tables:
                changed_tables.append(table)

    logging.info("notify_external: changed_tables=%s", changed_tables)

    dir_path = 'build/external'
    entries = os.listdir(dir_path)
    json_files = []
    for entry in entries:
        if entry.endswith('.json'):
            json_files.append("{0}/{1}".format(dir_path,entry))

    for json_file in json_files:
        with open(json_file) as input:
            config = json.load(input)

        impacted_tables = []
        for table_item in config["tables"]:
            if table_item["table"] in changed_tables:
                impacted_tables.append(table_item["table"])

        if len(impacted_tables) > 0:
            send_email(config["email"], version, impacted_tables)


def update_changelog(version, schema_changes, build_time):
    changelog_path = 'ERICddp_CXP9023713/src/main/resources/php/changelogs/changelog.json'
    with open(changelog_path) as input:
        changelogs = json.load(input)

    change = {
        'commits': get_commits(),
        'schema': schema_changes,
        'version': version,
        'timestamp': build_time
    }
    changelogs.append(change)

    with open(changelog_path, 'w') as outfile:
        json.dump(changelogs, outfile, indent=4)

def get_commits():
    last_commit =  os.getenv('GIT_PREVIOUS_SUCCESSFUL_COMMIT')
    if last_commit is None:
        print("ERROR: No value for GIT_PREVIOUS_SUCCESSFUL_COMMIT")
        sys.exit(1)

    log_command = [ 'git', 'log', '--pretty=format:%s', "{0}...".format(last_commit) ]
    result = subprocess.run(log_command, stdout=subprocess.PIPE)
    if result.returncode != 0:
        print("ERROR: git log command failed: exit code={0}".format(result.returncode))
        sys.exit(1)

    commit_messages = []
    for line in result.stdout.decode('utf-8').splitlines():
        if not line.startswith('[maven') and not line.startswith('appending build version'):
            if line not in commit_messages:
                commit_messages.append(line)

    logging.info("get_commits: commit_messages=%s", commit_messages)

    return commit_messages


def update_migrate(db, version):
    migrate_files = {
        'statsdb': 'migrate.sql',
        'ddpadmin': 'ddpadmin_migrate.sql'
    }
    path = "ERICddp_CXP9023713/src/main/resources/sql/{0}".format(migrate_files[db])
    content = []
    last_version = None
    with open(path) as input:
        for line in input.readlines():
            if line.startswith('-- END DDP'):
                parts = line.strip().split()
                version = parts[2]
                content.clear()
            else:
                content.append(line)

    #if last_version is None or last_version != version:
    #    with open(path, "a") as output:
    #        output.write("-- END {0}\n".format(version))
    print("update_migrate: Add {0} to {1}".format(version, migrate_files[db]))

    logging.info("get_commits: db=%s content=%s", db, content)

    return content


def parse_schema_change(db, change):
    summary = {
        'CREATE': [],
        'ALTER': [],
        'DROP': []
    }


    for line in change:
        match = re.match("^(CREATE|ALTER|DROP) TABLE (\S+)", line)
        if match:
            groups = match.groups()
            summary[groups[0]].append(groups[1])

    logging.info("parse_schema_change summary=%s", summary)

    return summary


parser = argparse.ArgumentParser()
parser.add_argument('--version', help="DDP Version")
parser.add_argument('--debug', help='debug logging', action="store_true")

args = parser.parse_args()

logging_level = logging.WARN
if args.debug:
    logging_level = logging.DEBUG
logging.basicConfig(level=logging_level)

now = datetime.datetime.now()
build_time = now.strftime("%Y-%m-%d %H:%M:%S")

schema_changes = {}
for db in [ 'statsdb', 'ddpadmin' ]:
    schema_change = update_migrate(db, args.version)
    if schema_change is not None and len(schema_change) > 0:
        schema_changes[db] = {
            'raw': schema_change,
            'summary': parse_schema_change(db, schema_change)
        }

update_changelog(args.version, schema_changes, build_time)
notify_external(args.version, schema_changes)


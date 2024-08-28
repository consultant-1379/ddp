#!/bin/bash

PVER=3.5.10
export PYTHON_HOME=/proj/ciexadm200/tools/python-${PVER}
if [ ! -d ${PYTHON_HOME} ] ; then
    echo "ERROR: ${PYTHON_HOME} doesn't exist"
    ls /proj/ciexadm200/tools 
    exit 1
fi

if [ "${JOB_NAME}" != "ddp_Release" ] ; then
    exit 0
fi

# Create/checkout local master branch
git checkout -b master
if [ $? -ne 0 ] ; then
    echo "ERROR: Non-zero exit for git checkout -b master"
    exit 1
fi

VERSION=$(egrep SNAPSHOT pom.xml | sed -e 's/^[^0-9]*//' -e 's/-SNAP.*//')
echo "INFO: Version $VERSION"
echo "INFO: GIT_PREVIOUS_SUCCESSFUL_COMMIT: $GIT_PREVIOUS_SUCCESSFUL_COMMIT"

if [ "${GIT_PREVIOUS_SUCCESSFUL_COMMIT}" = "null" ] ; then
    echo "WARN: GIT_PREVIOUS_SUCCESSFUL_COMMIT not set, changelog not updated"
else
    export ENM_PY=/proj/ciexadm200/tools/enm_py-${PVER}_reqmnts-1.0
    PVER_SHORT=$(echo ${PVER} | sed 's/\.[0-9]*$//')
    export PYTHONPATH=$ENM_PY/usr/lib/python${PVER_SHORT}/site-packages
    ip addr
    openssl s_client -connect smtp-central.internal.ericsson.com:25 -starttls smtp
    ${PYTHON_HOME}/bin/python build/build.py --version "DDP-${VERSION}" --debug
    if [ $? -ne 0 ] ; then
        echo "ERROR: Non-zero exit for build.py"
        exit 1
    fi

    git add ERICddp_CXP9023713/src/main/resources/php/changelogs/changelog.json
    if [ $? -ne 0 ] ; then
        echo "ERROR: Non-zero exit for git add changelog.json"
        exit 1
    fi
fi

MIGRATE_FILES="migrate.sql ddpadmin_migrate.sql"
for MIGRATE_FILE in ${MIGRATE_FILES} ; do
    FILENAME="./ERICddp_CXP9023713/src/main/resources/sql/${MIGRATE_FILE}"
    egrep "^-- END DDP-$VERSION" $FILENAME > /dev/null
    if [ $? -eq 0 ] ; then
        echo "INFO: Version $VERSION already in $FILENAME"
    else
        echo "INFO: Updating $FILENAME"
        echo "-- END DDP-${VERSION}" >> $FILENAME
        git add $FILENAME
        if [ $? -ne 0 ] ; then
            echo "ERROR: Non-zero exit for git add ${FILENAME}"
            exit 1
        fi
    fi
done

git commit -m "[appending build version $VERSION to migrate files]"
if [ $? -ne 0 ] ; then
    echo "ERROR: Non-zero exit for git commit"
    exit 1
fi


git push ${GERRIT_CENTRAL}/${REPO} master
if [ $? -ne 0 ] ; then
    echo "ERROR: Non-zero exit for git push"
    exit 1
fi

exit 0

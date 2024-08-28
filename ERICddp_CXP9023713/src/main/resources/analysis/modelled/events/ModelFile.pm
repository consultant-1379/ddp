package ModelFile;

use strict;
use warnings;

use Data::Dumper;
use XML::LibXML;

use File::Basename;
use lib dirname($0) . "/modules";

sub processModelFile($$) {
    my ($modelFilePath,$xsd_doc) = @_;

    my $dom = XML::LibXML->load_xml(location => $modelFilePath, XML_LIBXML_LINENUMBERS => 1);
    $xsd_doc->validate($dom) == 0 or die "Validation failed for $modelFilePath";

    my %model = ();

    my $modelledEvents = $dom->findnodes("/modelledevents")->[0];

    my $r_table = $modelledEvents->findnodes("table")->[0];
    my $timecol = $r_table->findvalue('@timecol');
    if ( ! defined $timecol || $timecol eq '' ) {
        $timecol = 'time';
    }
    my %table = (
        'name' => $r_table->findvalue('@name'),
        'keycol' => [],
        'timecol' => $timecol
        );
    foreach my $r_keycol ( $r_table->findnodes("keycol") ) {
        my $refnamecol = $r_keycol->getAttribute('refnamecol');
        if ( (! defined $refnamecol) || ($refnamecol eq '') ) {
            $refnamecol = 'name';
        }
        my %keyColumn = (
            'name' => $r_keycol->getAttribute('name'),
            'reftable' => $r_keycol->getAttribute('reftable'),
            'refnamecol' => $refnamecol
            );
        my $reffiltercol = $r_keycol->getAttribute('reffiltercol');
        if ( defined $reffiltercol ) {
            $keyColumn{'reffiltercol'} = $reffiltercol;
        }
        push @{$table{'keycol'}}, \%keyColumn;
    }
    $model{'table'} = \%table;

    my %eventsByName = ();
    foreach my $r_event ( $modelledEvents->findnodes("events/event") ) {
        my $name = $r_event->findvalue('@name');
        my $time_source = $r_event->findvalue('@time_source');
        if ( ! defined $time_source || $time_source eq '') {
            $time_source = 'timestamp';
        }
        my @metrics = ();
        foreach my $r_metric ( $r_event->findnodes("metric") ) {
            my %metric = (
                'source' => $r_metric->findvalue('@source'),
                'target' => $r_metric->findvalue('@target')
                );
            foreach my $name ( 'scale', 'filtervalue', 'store', 'convert', 'filteridle' ) {
                my $value = $r_metric->findvalue('@' . $name);
                if ( defined $value && $value ne '') {
                    $metric{$name} = $value;
                }
            }
            push @metrics, \%metric;
        }

        my @properties = ();
        foreach my $r_property ( $r_event->findnodes("property") ) {
            my %property = (
                'name' => $r_property->getAttribute('name'),
                'type' => $r_property->getAttribute("xsi:type")
            );
            if ( $property{'type'} eq 'fixedproperty' ) {
                $property{'value'} = $r_property->getAttribute('value');
            }
            push @properties, \%property;
        }

        $eventsByName{$name} = {
            'name' => $name,
            'time' => $time_source,
            'metric' => \@metrics,
            'properties' => \@properties
        };

        my $explode_array = $r_event->findvalue('@explode_array');
	if ( $::DEBUG > 8 ) { print Dumper("ModelFile::processModelFile explode_array", $explode_array); }
        if ( defined $explode_array && $explode_array ne '') {
            $eventsByName{$name}->{'explode_array'} = $explode_array;
        }
    }

    $model{'events'} = \%eventsByName;

    my $r_hooks = $modelledEvents->findnodes("hooks")->pop();
    if ( defined $r_hooks ) {
        my %hooks = (
            'module' => $r_hooks->getAttribute('module')
            );
        foreach my $r_hook ( $r_hooks->findnodes("hook") ) {
            $hooks{$r_hook->to_literal()} = 1;
        }
        $model{'hooks'} = \%hooks;
    }

    my $r_merge = $modelledEvents->findnodes("merge")->pop();
    if ( defined $r_merge ) {
        my @groupby = ();
        foreach my $group ( $r_merge->findnodes("grouping/groupby") ) {
            push @groupby, $group->getAttribute("name");
        }
        $model{'merge'} = {
            'groupby' => \@groupby,
        }
    }

    my $r_aggregate = $modelledEvents->findnodes("aggregate")->pop();
    if ( defined $r_aggregate ) {
        my $interval = $r_aggregate->getAttribute("interval");
        if ( ! defined $interval ) {
            $interval = 1;
        }

        my @groupby = ();
        foreach my $group ( $r_aggregate->findnodes("grouping/groupby") ) {
            my $name = $group->getAttribute("name");
            my $mand = $group->getAttribute("mandatory");

            # Set $mand to true if it is not set to support existing files
            if ( defined $mand && $mand eq "true" ) {
                $mand = "true";
            } else {
                $mand = "false";
            }

            my %groupData = ( "name" => $name, "mandatory" => $mand );
            push @groupby, \%groupData;
        }

        my @aggregations = ();
        foreach my $aggregation ( $r_aggregate->findnodes("aggregations/aggregation") ) {
            push @aggregations, {
                'type' => $aggregation->getAttribute("type"),
                'name' => $aggregation->getAttribute("name")
            };
        }

        $model{'aggregate'} = {
            'interval' => $interval,
            'groupby' => \@groupby,
            'aggregations' => \@aggregations
        };
    }

    my %services = ();
    foreach my $r_service ( $modelledEvents->findnodes("services/service") ) {
        $services{$r_service->getAttribute("name")} = 1;
    }
    if ( %services ) {
        $model{'services'} = \%services;
    }

    return \%model;
}

sub getModels {
    my ($modelDir,$r_models, $xsd_doc) = @_;

    my @modelFiles = ();
    opendir(my $dh, $modelDir) or die "Cannot open $modelDir";
    foreach my $file (readdir($dh)) {
        if ( $file !~ /^\./ ) {
            my $path = $modelDir . "/" . $file;
            if ( $path =~ /\.xml$/ ) {
                push @modelFiles, $path;
            } elsif ( -d $path ) {
                getModels($path, $r_models, $xsd_doc);
            }
        }
    }
    closedir($dh);

    if ( $::DEBUG > 3 ) { print Dumper("getModels: modelFiles", \@modelFiles); }
    foreach my $modelFilePath ( @modelFiles ) {
        push @{$r_models}, processModelFile($modelFilePath,$xsd_doc);
    }
}

1;

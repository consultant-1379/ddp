package TOR::sync_status_changes;

use strict;
use warnings;

use StatsCommon;
use Data::Dumper;
use Storable qw(dclone);
use JSON;
use StatsTime;

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub preprocess($$$$$$$$) {
    my ($self, $site, $r_model, $r_srvIds, $r_events, $r_hostToSg, $r_incr, $date) = @_;

    my $outDir = getOutDir($site, $date);

    if ( ! -d $outDir ) {
        # mkdir returns true if it succeeds
        if ( ! mkdir $outDir ) {
            print "TOR::sync_status_changes Error creating dir $outDir";
            return $r_events;
        }
    }

    my %ne_map = ();
    my %seriesByType = ();

    foreach my $NodeType ( ('comecimmscm', 'mscmapg') ) {
        my $statsHash = {
            'UNSYNCHRONIZED' => [],
            'PENDING' => [],
            'TOPOLOGY' => [],
            'DELTA' => [],
            'SYNCHRONIZED' => []
        };

        $seriesByType{$NodeType} = $statsHash;
    }

    foreach my $element ( @{$r_events} ) {
        my $sg = $r_hostToSg->{$element->{host}};
        my $epoch = getDateTimeInMilliSeconds( $element->{timestamp} );
        my $node =  $element->{data}->{Node};
        my $syncStatus = $element->{data}->{SyncStatus};
        my $nodeId = getId($node, $sg, \%ne_map);

        push @{$seriesByType{$sg}->{$syncStatus}}, [$epoch, $nodeId];
    }

    createMappedEventDataFile($outDir, \%seriesByType );
    createNodeIdMapFile($outDir, \%ne_map);

    return $r_events;
}


sub getOutDir($$) {
    my ($site, $date) = @_;

    my $dirDate = formatTime(parseTime($date . " 00:00:00",$StatsTime::TIME_SQL), $StatsTime::TIME_DDMMYY);
    return '/data/stats/tor/' . $site . '/analysis/' . $dirDate . '/cm/';
}

sub getId($$$) {
    my ($node, $sg, $r_ne_map) = @_;
    my ($id, $max);

    if ( !exists $r_ne_map->{$sg}{$node} ) {
        $max = scalar %{$r_ne_map->{$sg}};
        $id = $max + 1;
        $r_ne_map->{$sg}{$node} = $id;
    } else {
        $id = $r_ne_map->{$sg}{$node};
    }

    return $id;
}

sub createNodeIdMapFile($$) {
    my ($outDir, $r_ne_map) = @_;
    my ($json, $filename);

    foreach my $sg ( keys %{$r_ne_map} ) {
        my @data = ();
        while ( my($ne, $idx) = each %{$r_ne_map->{$sg}} ) {
            my %d = ( "index" => $idx, "ne" => "$ne" );
            push(@data, \%d);
        }
        $json = encode_json(\@data);

        $filename = $outDir . 'index_' . $sg . '_event.json';
        open(FH, '>', $filename) or die $!;
        print FH $json;
        close(FH);
    }
}

sub createMappedEventDataFile($$) {
    my ($outDir, $r_seriesByType) = @_;

    foreach my $servGrp ( ('comecimmscm', 'mscmapg') ) {
        my $plotFile = $outDir . "syncStatus_" . $servGrp . "_event.json";
        open OUTPUT, ">$plotFile" or die "Cannot open $plotFile";
        while ( my ($syncStatus, $r_events) = each %{$r_seriesByType->{$servGrp}} ) {
            if ( $#{$r_events} > -1 ) {
                print OUTPUT encode_json({ 'name' => $syncStatus, 'data' => $r_events }), "\n";
            }
        }
        close OUTPUT;
    }
}

1;

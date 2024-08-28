package TOR::cm::AutoID;

use strict;
use warnings;

use Data::Dumper;
use Storable qw(dclone);

use StatsDB;
use Instr;

#
# handler interface functions
#
sub new {
    my $klass = shift;
    my $self = bless {}, $klass;
    return $self;
}

sub prestore($$$$) {
    my ($self,
        $r_cliArgs,$dbh,$r_model,
        $r_dataSets,$r_columnMap) = @_;

    #
    # We get metrics which are a series of tuples
    # - dps<MoType>CreatedEventCount
    # - dps<MoType>AttributeChangedEventCount
    # - dps<MoType>DeletedEventCount
    # We have to spilt these up in to multiple dataSets, one per MO Type
    #
    my %moTypeHash = ();
    while ( my ($source,$target) = each %{$r_columnMap} ) {
        my ($metricType) = $target =~ /^dpsFunction(\S+)/;
        my ($moType) = $source =~ /^dps(\S+)$metricType$/;

        $moTypeHash{$moType}->{$target} = $source;
    }
    my @moTypes = keys %moTypeHash;
    if ( $::DEBUG > 4 ) { print "TOR::common::DPS::prestore moTypes=" . join(",",@moTypes) . "\n"; }

    my %samplesByMoType = ();
    foreach my $moType ( @moTypes ) {
        $samplesByMoType{$moType} = [];
    }

    #
    # Iterate through samples and split into samples per MO Type
    #
    my $r_samples = $r_dataSets->[0]->{'samples'};
    foreach my $r_sample ( @{$r_samples} ) {
        foreach my $moType ( @moTypes ) {
            my %moTypeSample = ( 'time' => $r_sample->{'time'},
                                 'timestamp' => $r_sample->{'timestamp'} );
            while ( my ($target,$source) = each %{$moTypeHash{$moType}} ) {
                my $value = $r_sample->{$source};
                if ( defined $value ) {
                    $moTypeSample{$target} = $value;
                }
            }
            push @{$samplesByMoType{$moType}}, \%moTypeSample;
        }
    }

    my @dataSets = ();
    my @filterAttributes = ( 'dpsFunctionCreatedEventCount', 'dpsFunctionAttributeChangedEventCount', 'dpsFunctionDeletedEventCount' );
    foreach my $moType ( @moTypes ) {
        my $r_properties = dclone($r_dataSets->[0]->{'properties'});
        $r_properties->{'moid'} = { 'sourcevalue' => $moType };
        # We also filter idle values here as most of the MO type probably aren't active
        push @dataSets, { 'properties' => $r_properties, 'samples' => instrFilterIdle($samplesByMoType{$moType},\@filterAttributes) };
    }

    # Update the column mapping
    foreach my $key ( keys %{$r_columnMap} ) {
        delete $r_columnMap->{$key};
    }
    foreach my $attribute ( @filterAttributes ) {
        $r_columnMap->{$attribute} = $attribute;
    }

    return \@dataSets;
}

1;

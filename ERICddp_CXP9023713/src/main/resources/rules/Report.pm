package Report;

use strict;
use warnings;

use XML::Simple;
use XML::LibXML;
use File::Basename;
use Data::Dumper;
use DBI;

use StatsDB;

sub new {
    my ($klass,$site_type,$reportId,$dbh) = @_;
    my $self = bless {}, $klass;
    $self->{'site_type'} = $site_type;
    $self->{'reportid'} = $reportId;
    $self->{'dbh'} = $dbh;

    my $res = 0;
    $res = $self->_load();

    if ( $res eq 0 ) {
        return undef;
    }
    return $self;
}

sub getRuleInsts {
    my ($self) = @_;

    return $self->{'report'}->{'ruleinst'};
}

sub getRuleDef($$) {
    my ($self,$r_ruleInst) = @_;

    return $r_ruleInst->{'_ruledef'};
}

sub getRuleDescription($$) {
    my ($self,$r_ruleInst) = @_;

    if ( exists $r_ruleInst->{'desc'} ) {
        return $r_ruleInst->{'desc'};
    } else {
        return $r_ruleInst->{'_ruledef'}->{'desc'};
    }
}

sub getConditionDef($$) {
    my ($self,$r_condition) = @_;

    return $r_condition->{'_def'};
}

sub getReportId {
    my ($self) = @_;
    return $self->{'reportid'};
}

sub _linkConditional {
    my ($self, $r_conditionalContainer, $r_conditionDefMap, $name) = @_;

    my $r_conditional = $r_conditionalContainer->{'conditional'};
    if ( ! defined $r_conditional  ) {
        return;
    }

    foreach my $r_condition ( @{$r_conditional->{'condition'}} ) {
        my $conditiondefName = $r_condition->{'def'};
        my $r_conditionDef = $r_conditionDefMap->{$conditiondefName};
        (defined $r_conditionDef) || die "Unknown conditiondef $conditiondefName in $name";
        $r_condition->{'_def'} = $r_conditionDef;
    }
}

sub _load {
    my ($self) = @_;

    my $rulesDir = dirname(__FILE__);
    if ( $::DEBUG > 1 ) { print "Report::_load rulesDir=$rulesDir\n"; }

    my $ruleDefsFile = $rulesDir . "/rules.xml";
    my $rulesetXSD = XML::LibXML::Schema->new(location => $rulesDir . "/ruleset.xsd");
    $self->_validateXml($ruleDefsFile,$rulesetXSD);
    $self->{'def'} = $self->_parseXML($ruleDefsFile);

    my $ruleInstsFile = $rulesDir . "/hc_" . lc($self->{'site_type'}) . ".xml";
    my $reportXSD = XML::LibXML::Schema->new(location => $rulesDir . "/report.xsd");
    $self->_validateXml($ruleInstsFile,$reportXSD);
    my $hcXML = undef;
    {
        open my $fh, '<', $ruleInstsFile or die;
        $/ = undef;
        $hcXML = <$fh>;
        close $fh;
    }
    $self->{'report'} = $self->_parseXML($hcXML);

    my $repName = 'Default';
    my $repId = $self->{'reportid'};

    if ( $repId != 0 ) {
        my $query = "SELECT content, reportname FROM ddpadmin.ddp_custom_reports WHERE id = $repId";
        my $r_resultRows = dbSelectAllArr($self->{'dbh'}, $query)
            or die "Failed to get custom reports";

        my $r_customReport = XMLin(
            $r_resultRows->[0]->[0],
            ForceArray => [
                'disabledrule',
                'ruleinst',
                'threshold',
                'condition',
                'parameter',
                'subscriber',
                'conditiondef'
            ],
            keyattr => []
        );

        $repName = $r_resultRows->[0]->[1];
        if ( exists $r_customReport->{'disabledrule'} ) {
            my %disabledNames = ();
            foreach my $disabledrule ( @{$r_customReport->{'disabledrule'}} ) {
                $disabledNames{$disabledrule} = 1;
            }
            my @enabledRuleInsts = ();
            foreach my $r_ruleInst ( @{$self->{'report'}->{'ruleinst'}} ) {
                if ( ! exists $disabledNames{$r_ruleInst->{'rulename'}} ) {
                    push @enabledRuleInsts, $r_ruleInst;
                }
            }
            $self->{'report'}->{'ruleinst'} = \@enabledRuleInsts;
        }

        if ( exists $r_customReport->{'ruleinst'} ) {
            my %customRuleInstByName = ();
            foreach my $r_ruleInst ( @{$r_customReport->{'ruleinst'}} ) {
                $customRuleInstByName{$r_ruleInst->{'rulename'}} = $r_ruleInst;
            }

            # Replace any existing rule with the same name with the custom one
            for ( my $index = 0; $index <= $#{$self->{'report'}->{'ruleinst'}}; $index++ ) {
                my $name = $self->{'report'}->{'ruleinst'}->[$index]->{'rulename'};
                my $r_customRuleInst = delete $customRuleInstByName{$name};
                if ( defined $r_customRuleInst ) {
                    $self->{'report'}->{'ruleinst'}->[$index] = $r_customRuleInst;
                }
            }

            # Add any remaining custom rule
            foreach my $r_customRuleInst ( values %customRuleInstByName ) {
                push @{$self->{'report'}->{'ruleinst'}}, $r_customRuleInst;
            }
        }
    }

    #
    # Link any rules with conditions to the conditiondef
    #
    my %conditionDefMap = ();
    foreach my $r_conditiondef ( @{$self->{'def'}->{'conditiondef'}} ) {
        $conditionDefMap{$r_conditiondef->{'name'}} = $r_conditiondef;
    }

    my %ruleDefMap = ();
    foreach my $r_ruleDef ( @{$self->{'def'}->{'rule'}} ) {
        my $ruleDefName = $r_ruleDef->{'name'};
        $ruleDefMap{$ruleDefName} = $r_ruleDef;
        $self->_linkConditional($r_ruleDef, \%conditionDefMap, $ruleDefName);
    }

    my $ruleDefsValid = 1;
    #
    # Link ruleinst to the ruledef
    #
    foreach my $r_ruleInst ( @{$self->{'report'}->{'ruleinst'}} ) {
        my $ruleName = $r_ruleInst->{'rulename'};
        my $ruleDefName = $ruleName;
        if ( exists $r_ruleInst->{'ruledef'} ) {
            $ruleDefName = $r_ruleInst->{'ruledef'};
        }
        my $r_ruleDef = $ruleDefMap{$ruleDefName};
        if ( ! defined $r_ruleDef ) {
            $ruleDefsValid = 0;
            print "Report $repName (id: $repId) failed due to undefined rule $ruleDefName\n";
        }
        $r_ruleInst->{'_ruledef'} = $r_ruleDef;

        # If the ruleinst has any
        $self->_linkConditional($r_ruleInst, \%conditionDefMap, $ruleName);
    }

    if ( $::DEBUG > 5 ) { print Dumper("Report::load: id=$repId", $self->{'report'}); }

    return $ruleDefsValid;
}

sub _validateXml {
    my ($self,$xmlPath,$xsd_doc) = @_;

    if ( $::DEBUG > 3 ) { print "Report::validateXml xmlPath=$xmlPath\n;" }
    my $dom = XML::LibXML->load_xml(location => $xmlPath, XML_LIBXML_LINENUMBERS => 1);
    $xsd_doc->validate($dom) == 0 or die "Validation failed for $xmlPath";
}

sub _parseXML {
    my ($self,$xmlTxt) = @_;

    my $xml = new XML::Simple;
    my $r_data = $xml->XMLin($xmlTxt, keyattr => [], forcearray => [ 'threshold', 'parameter', 'subscriber', 'condition', 'conditiondef', 'ruleinst' ] );

    if ( $::DEBUG > 5 ) { print Dumper("readXML: r_data", $r_data); }
    return $r_data;
}

1;

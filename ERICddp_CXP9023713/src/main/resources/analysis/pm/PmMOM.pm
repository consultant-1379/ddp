package PmMOM;

require Exporter;
our @ISA = ("Exporter");
our @EXPORT    = qw( getPdf );

use strict;
use Carp;
use LWP;
use Data::Dumper;

our $URL_BASE= "http://wrn.ericsson.se/~emogroup/mom";

sub getPdf
{
    my ($predef_dir,$neMimVer) = @_;

    my $r_pdfMap = {};

    my ($letter,$major,$minor) = $neMimVer =~ /^v([A-Z])\.(\d+)\.(\d+)$/;
    my $fileName = sprintf "%s/%s%s.%s.pdf", $predef_dir, $letter,$major,$minor;

    if ( $main::DEBUG > 4 ) { print "getPdf: looking for fileName=$fileName for neMimVer=$neMimVer\n"; }

    if ( ! -r $fileName ) {
	# Workaround for the fact that the RNC Mom forum site now required a login
	my $hackFileName = sprintf( "%s/xml/rnc_node_mim_%s_%d_%d.xml", $predef_dir, lc($letter), $major, $minor);
	if ( -r $hackFileName ) {
            extractPdf( $hackFileName, $fileName );
	} else {
	    my %missingVers = ();

	    my $missingListFile = sprintf( "%s/xml/missing.txt", $predef_dir );
	    if ( -r $missingListFile ) {		
		open MISSING, $missingListFile or die "Failed to open $missingListFile for reading";
		while ( my $line = <MISSING> ) {
		    chop $line;
		    $missingVers{$line} = 1;
		}
		close MISSING;
	    }

	    my $thisVer = sprintf("%s.%d.%d", lc($letter), $major, $minor);
	    if ( ! exists $missingVers{$thisVer} ) {
		$missingVers{$thisVer} = 1;
		open MISSING, ">$missingListFile" or die "Failed to open $missingListFile for writing";
		foreach my $ver ( keys %missingVers ) {
		    print MISSING "$ver\n";
		}
		close MISSING;
	    }
	}
	# } else {
	#     my $dir = "/tmp";
	#     if ( exists $ENV{'TMP_DIR'} ) {
	# 	$dir = $ENV{'TMP_DIR'};
	#     }
	#     my $xmlFile = $dir . "/" . $neMimVer . ".xml";
	#     if ( getMOM( $letter,$major,$minor, $xmlFile ) )
	#     {
	# 	extractPdf( $xmlFile, $fileName );
	#     }
	# }
    }

    if ( open PDF, $fileName )
    {
        while ( my $line = <PDF> )
        {
            if ( $line !~ /^\#/ )
            {
                chop $line;
                my @fields = split / /, $line;

                $r_pdfMap->{$fields[0]}->{$fields[1]} = $fields[2];
            }
        }
        close PDF;

        if ( $main::DEBUG > 5 ) { print Dumper("getPdf: pdfMap", $r_pdfMap); }
    }
    else
    {
        if ( $main::DEBUG > 0 ) { print "getPdf: Could not find PDF counters for mim version $neMimVer\n"; }
    }

    return $r_pdfMap;
}

sub getMOM
{
    my ( $letter,$major,$minor, $outputFile ) = @_;

    my $fileName = sprintf( "rnc_node_mim_%s_%d_%d.xml", lc($letter), $major, $minor);

    my $browser = LWP::UserAgent->new;
    $browser->timeout(15);
    my $gotIt = 0;
    for ( my $p = 5; $p < 10 && ! $gotIt ; $p++ )
    {
        my $url = sprintf( "%s/p%d", $URL_BASE, $p );

        if ( $p == 5 )
        {
            $url .= "/wendy_xml_cache";
        }
        $url .= "/" . $fileName;

        printf "Trying %s...", $url;

        my $response = $browser->get( $url, ':content_file' => $outputFile );
        printf "%s\n", $response->status_line;

        if ( $response->is_success )
        {
            $gotIt = 1;
        } else {
            $response->status_line =~ /^([0-9][0-9][0-9]) .*/;
            if ($1 == 500) {
                # no point in continuing, server is down
                print "ERROR: cannot get " . $url . "\n";
                return 0;
            }
        }
    }

    return $gotIt;
}

sub extractPdf
{
    my ($xmlFile,$pdfFile) = @_;

    my %cntrInfo = ();

    open XML, $xmlFile or confess "Cannot open $xmlFile";
    while ( my $line = getXmlLine() )
    {
        if ( $line =~ /^\s+\<class name=\"([^\"]+)\"/ )
        {
            my $moc = $1;
            if ( $main::DEBUG > 6 ) { print "extractPdf: found moc $moc\n" };

            my @cntrList = ();
            while ( ($line = getXmlLine()) && ($line !~ /^\s+\<\/class/) )
            {
                if ( $line =~ /^\s+\<attribute name=\"([^\"]+)\"/ )
                {
                    my $attrName = $1;
                    if ( $main::DEBUG > 7 ) { print "extractPdf: found attribute $attrName\n" };

                    if ( $attrName =~ /^pm/ )
                    {
                        if ( $main::DEBUG > 6 ) { print "extractPdf: found cntr $attrName\n" };

                        my @description = ();
                        my $numValue = 0;
                        while ( ($line = getXmlLine()) && ($line !~ /\/description/) )
                        {
                            if ( $line =~ /(\d+)\]/ )
                            {
                                $numValue = $1 + 1;
                            }
                        }

                        if ( $main::DEBUG > 6 ) { print "extractPdf: end description numValue=$numValue\n" };

                        my $pdf = 0;			
                        while ( ($line = getXmlLine()) && ($line !~ /dataType/) ) {}

                        $line = getXmlLine();

                        if ( $main::DEBUG > 6 ) { print "extractPdf: check for sequence in dataType $line" };

                        if ( $line =~ /sequence/ )
                        { 
                            $pdf = 1;
                        }


                        if ( $main::DEBUG > 6 ) { print "extractPdf: is pdf = $pdf\n" };

                        getXmlLine(); # seq type
                        $line = getXmlLine();
                        if ( $line =~ /maxLength\>(\d+)/ )
                        {
                            $numValue = $1;
                            if ( $main::DEBUG > 6 ) { print "extractPdf: numValue = $numValue\n" };
                        }


                        my $r_Cntr = {
                            'name' => $attrName
                        };

                        if ( $pdf )
                        {
                            $r_Cntr->{'type'} = 'pdf';
                            $r_Cntr->{'pdfvalues'} = $numValue;
                        }
                        else
                        {
                            $r_Cntr->{'type'} = 'normal';
                        }

                        push @cntrList, $r_Cntr;
                    }
                }
            }
            if ( $main::DEBUG > 6 ) { print "extractPdf: end class\n" };

            if ( $#cntrList > - 1 )
            {
                $cntrInfo{$moc} = \@cntrList;
            }
        }
    }
    close XML;

    if ( $main::DEBUG > 4 ) { print Dumper("extractPdf: cntrInfo", \%cntrInfo); }

    open PDF, ">$pdfFile" or confess "Failed to open $pdfFile";
    foreach my $moc ( keys %cntrInfo )
    {
        foreach my $r_Cntr ( @{$cntrInfo{$moc}} )
        {
            if ( $r_Cntr->{'type'} eq 'pdf' )
            {
                printf PDF "%s %s %d\n", $moc, $r_Cntr->{'name'}, $r_Cntr->{'pdfvalues'};
            }
        }
    }
    close PDF;
}

sub getXmlLine
{
    my $line;
    while ( ($line = <XML>) && ($line =~ /^\s*$/) )
    {
        if ( $main::DEBUG > 10 ) { print "getXmlLine: blank $line"; }
    }

    if ( $main::DEBUG > 8 ) { print "getXmlLine: $line"; }

    return $line;
}

1;

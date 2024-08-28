package      trimTxName;
require      Exporter;

our @ISA       = qw(Exporter);
our @EXPORT    = qw(trimTxName $TRIM_TX_DEBUG);    # Symbols to be exported by default
our $VERSION   = 1.00;         # Version number

### Include your variables and functions here

our $TRIM_TX_DEBUG = 0;

sub trimTxName
{
    my ($txName) = @_;

    if ( $TRIM_TX_DEBUG > 2 ) { print "trimTxName: in txName=$txName\n"; }

    if ( $txName =~ /^(.*)\s+$/ )
    {
	$txName = $1;
    }

    if ( $txName =~ /NullTX.*_\d+$/ )
    {
       ($txName) = $txName =~ /(.*)_\d+$/;
    }

    my $remote = 0;
    if ( $txName =~ /^RemoteTX_(.*)/ )
    {
	$txName = $1;
	$remote = 1;
    }

    #
    # In this section we do a full replacement on the txName
    #
    if ( $txName =~ /^IncrSync/ ) # Need to trim the name
    {
        $txName = "IncrSync";
    }
    elsif ( $txName =~ /^BrfConfigurationAgent/ )
    {
	$txName = "BrfConfigurationAgent";
    }
    elsif ( $txName =~ /(.*)RNO_BRF_([^:]+):\d+/ )
    {
	$txName = $1 . "RNO_BRF_" . $2;
    }
    # WMA_Timeout=600_4688_[TIMESTAMP]
    elsif ( $txName =~ /WMA_Timeout/ )
    {
       $txName = "WMA";
    }
    elsif ( $txName =~ /^WMA_([^:]+)/ )
    {
	$txName = "WMA_" . $1;    
    }
    elsif ( $txName =~ /snad.*@/ )
    {
        $_ = $txName;
        my ($type) = /.*\.([^@]*)@/;
        $txName = "SNAD:" . $type;
    }
    elsif ( $txName =~ /MoProxyFlush :\d+/ )
    {
        $_ = $txName;
        my ($pre,$post) = /^(.*)MoProxyFlush :\d+(.*)$/;
        $txName = $pre . "MoProxyFlush :[NUM]" . $post;
    }
    elsif ( $txName =~ /^Automatic_ConsistencyChecker_(\S)/ )
    {
	$txName = "Automatic_ConsistencyChecker_" . $1;
    }
    elsif ( $txName =~ /^PMS_BadNEFilePathHandler/ )
    {
	$txName = "PMS_BadNEFilePathHandler";
    }
    elsif ( $txName =~ /cstest/ )
    {
	$txName = "cstest";
    }
    elsif ( $txName =~ /^PMS_SEG_CSAccess_searchForMo_/ )
    {
	$txName = "PMS_SEG_CSAccess_searchForMo";
    }
    elsif ( $txName =~ /^TelisReadTransaction[A-Za-z]/ )
    {
	$txName = 'TelisReadTransaction[MO]';
    }
    elsif ( $txName =~ /^TelisReadMo_/ )
    {
	$txName = "TelisReadMo";
    }
    # NEAD 5.2+
    elsif ( $txName =~ /^NEAD_DirtyAttributeWriter_\d+/ )
    {
	$txName = "NEAD_DirtyAttributeWriter";
    }
    elsif ( $txName =~ /^NotificationIntroducer:execute\S+/ )
    {
	$txName = "NotificationIntroducer:execute";
    }
    elsif ( $txName =~ /^WriteToMirror:run_/ )
    {
	$txName = "WriteToMirror:run";
    }
    elsif ( $txName =~ /^Synchronizer.execute:/ )
    {
	$txName = "Synchronizer.execute";
    }
    #
    # In this section, we replace parts of the txName 
    #
    else
    {
	if ( $txName =~ /^cms_nead_seg:AttributeSynchronizer.synchronizeAttributes/ )
	{
	    $txName = 'cms_nead_seg:AttributeSynchronizer.synchronizeAttributes';
	}
	elsif ( $txName =~ /^cms_nead_seg:/ )
	{
	    $_ = $txName;
	    ($txName) = /^(.*):.*$/;
	}
	elsif ( $txName =~ /^cms:/ )
	{
	    $_ = $txName;
	    ($txName) = /^(.*):.*$/;
	}
    # cslib / R5.2+ specific from here
    elsif ( $txName =~ /^(.*)_SubNetwork=.*$/ ) {
        $txName = $1;
    }
    elsif ( $txName =~ /^(.*):\@SID=\d{1,6}/ ) {
        $txName = $1;
    }

	if ( $txName =~ /\d{13,13}/ )
	{
	    # Strip out timestamp
	    $_ = $txName;
	    my ($pre,$post) = /^(.*)\d{13,13}(.*)$/;
	    $txName = $pre . "[TIMESTAMP1]" . $post;
	}
	if ( $txName =~ /\d{4,4}\.\d{2,2}\.\d{2,2} - \d{2,2}:\d{2,2}:\d{2,2}/ )
	{
	    # Stupid FMS/PMS timestamp
	    $_ = $txName;
	    my ($pre,$post) = /^(.*)\d{4,4}\.\d{2,2}\.\d{2,2} - \d{2,2}:\d{2,2}:\d{2,2}(.*)$/;
	    $txName = $pre . "[TIMESTAMP2]" . $post;
	}
	# 
	if ( $txName =~ /^(.*)\d{2,2}:\d{2,2}:\d{2,2}\.\d{3,3}(.*)/ )
	{
	    $txName = $1. "[TIMESTAMP3]" . $2;
	}

	# 4/6/05 3:30:08 PM
	if ( $txName =~ /^(.*)\d+\/\d+\/\d+\s+\d+:\d+:\d+ [APM]{2,2}(.*)/ )
	{
	    $txName = $1. "[TIMESTAMP4]" . $2;
	}

	#13:52:13.897
	if ( $txName =~ /^(.*) \d{2,2}:\d{2,2}:\d{2,2}\.\d{3,3}(.*)/ )
	{
	    $txName = $1 . "[TIMESTAMP5]" . $2;
	}
        #  Wed Oct 05 14:15:16 BST 2005
	if ( $txName =~ /^(.*)\S{3,3} \S{3,3} \d{2,2} \d{2,2}:\d{2,2}:\d{2,2} \S{3,3} \d{4,4}(.*)/ )
	{
	    $txName = $1. "[TIMESTAMP6]" . $2;
	}

	if ( $txName =~ /THREAD_(\d+)/ )
	{
	    # In newer logs, the thread num is in twice in NEAD logs
	    my $threadNum = $1;
	    $txName =~ s/_$threadNum/_\[NUM\]/g;
	}

	if ( $txName =~ /[:_]\d+$/ )
	{
	    $txName =~ s/[:_]\d+$/_\[NUM\]/;
	}

	$txName =~ s/getSourceType\([^\)]+\)/getSourceType\(\[FDN\]\)/;
	$txName =~ s/PA_ARNE_ARNEServer:\d+/PA_ARNE_ARNEServer:\[NUM\]/;
	$txName =~ s/^NSA.getStateForNode:.*/NSA.getStateForNode_\[NODE\]/;
	$txName =~ s/^NSA\.([A-Za-z]+)\d+$/NSA\.$1_\[NUM\]/;
	$txName =~ s/getRemoteNeadInfo\([^\)]+\)/getRemoteNeadInfo\(\[FDN\]\)/;

	$txName =~ s/.*:MA-RTX/MA-RTX/;
    }
    
    # HM69152: replace all numbers with [NUM]
    $txName =~ s/\d+/\[NUM\]/g;

    if ( $remote )
    {
	$txName = "RemoteTX_" . $txName;
    }

    if ( $TRIM_TX_DEBUG > 2 ) { print "trimTxName: out txName=$txName\n"; }

    return $txName;
}

1;

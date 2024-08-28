#!/usr/bin/env perl

#=====================================================================
### Script  : deleteOldESCSVFiles.pl
### Author  : Sourav Pujara (xsoupuj)
### Purpose : Whenever Team receives email alert regarding threshold exceeded for /data, this script can be executed
###           to delete the Elasticsearch csv files (eg 01.csv.gz, 01_complete.csv.gz or 01_partial.csv.gz) which are
###           older than X days. Also it restricts the deletion to only more than 60 days.
### Usage   : ./deleteOldESCSVFiles.pl  --count $count
###=====================================================================
use strict;
use warnings;
use Getopt::Long;
use Date::Format;
sub main() {

    my $count;
    my $result = GetOptions(
        "count=s" => \$count,
    );

    if( not defined $count) {
      print "Usage:  ./deleteOldESCSVFiles.pl --count \$count";
      exit;
    }

    if($count > 60 ) {
      deleteOldElasticSearchCSVFiles ($count);
    }
    else {
      print "No of days given are less than or equal to 60.\n";
    }

}

sub deleteOldElasticSearchCSVFiles($) {
    my($count) = @_;
    my($par_dir,$sub_dir);
    opendir ($par_dir,"/data/stats/tor") or die "Unable to open Parent Directory : $!";
    while (my $sub_folders = readdir($par_dir)) {
      next if( ! -d "/data/stats/tor/$sub_folders" || $sub_folders eq '.' || $sub_folders eq '..');
      my $analysisDir="/data/stats/tor/$sub_folders/analysis";
      opendir ($sub_dir,$analysisDir) or die "Unable to open Sub Directory : $!";
      while (my $file = readdir($sub_dir)) {
        if($file =~ /(\d{2})(\d{2})(\d{2})/) {
          my($dd,$mm,$yy) = ($1,$2,$3);
          my (undef,undef,undef,$mday,$mon,$year) = localtime;
          $year = $year+1900;
          $mon += 1;
          if (length($mon)  == 1) {$mon = "0$mon";}
          if (length($mday) == 1) {$mday = "0$mday";}
          my $today = "$year$mon$mday";
          my $DIFF=(`date +%s -d $today`-`date +%s -d 20$yy$mm$dd`)/86400;
          if ($DIFF > $count) {
            system("rm -rf $analysisDir/$file/enmlogs/*csv.gz");
          }
        }
      }
      closedir($sub_dir) or die "Unable to close Sub Directory : $!";
    }
    closedir($par_dir) or die "Unable to close Parent Directory : $!";;
}
main();

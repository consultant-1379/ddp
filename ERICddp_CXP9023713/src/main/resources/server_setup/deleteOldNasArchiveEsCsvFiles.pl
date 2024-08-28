#!/usr/bin/env perl

#=====================================================================
### Script  : deleteOldNasArchiveEsCsvFiles.pl
### Author  : Sourav Pujara (xsoupuj)
### Purpose : Whenever Team receives email alert regarding threshold exceeded for /nas, this script can be executed manually
###           to delete the Elasticsearch csv files (eg 01.csv.gz, 01_complete.csv.gz or 01_partial.csv.gz) from /nas/archive which are
###           older than X days. Also it restricts the deletion to only more than 60 days.
### Usage   : ./deleteOldNasArchiveEsCsvFiles.pl  --count $count --year $year  --getSiteList $getSiteList.
###           $getSiteList can be:
###           1. "all" : to delete CSV files from all the sites in /data/stats/tor/ for a specific DDP server
###           2. filename : This file can be created having sitenames separated by only newlines. For example, if you want to delete LMI_ENM300 and LMI_ENM400
###                         then create a file say /var/tmp/siteList and the content would be the following without any spaces:
###                         LMI_ENM300
###                         LMI_ENM400
###=====================================================================
use strict;
use warnings;
use Getopt::Long;
use Date::Format;
sub main() {

    my ($count,$year,$getSiteList);
    my $result = GetOptions(
        "count=s" => \$count,
        "year=s" => \$year,
        "getSiteList=s" => \$getSiteList,
    );

    if( not defined $count && $year && $getSiteList ) {
      print "Usage:  ./deleteOldNasArchiveEsCsvFiles.pl --count \$count --year \$year --getSiteList \$getSiteList";
      exit;
    }
    my @sites=();
    if( $getSiteList eq "all") {
      my $torDir="/data/stats/tor";
      opendir(DIR,$torDir) or die "Can not open $torDir $!";
      while (my $file = readdir(DIR)) {
        next if( $file eq '.' || $file eq '..');
        push @sites,$file;
      }
      closedir(DIR);
    } elsif( -f $getSiteList ) {

      open(DATA,"<$getSiteList");
      while(my $file = <DATA>) {
        chomp($file);
        push @sites,$file;
      }
      close(DATA);
    } else {

      print "Please give correct values for the option \"getSiteList\" ";
      exit;
    }
    if($count > 60 ) {
      deleteOldNasArchiveElasticSearchCSVFiles ($count,$year,\@sites);
    }
    else {
      print "No of days given are less than or equal to 60.\n";
    }

}

sub deleteOldNasArchiveElasticSearchCSVFiles($$$) {
    my($count,$year,$sites) = @_;
    my($site,$dir);
    foreach $site(@$sites) {
      my $sitesDir="/nas/archive/$year/tor/$site/analysis";
      if(! -d $sitesDir ) {
        print "$sitesDir does not exists \n";
        next;
      }
      opendir ($dir,$sitesDir) or die "Unable to open Sub Directory : $!";
      while (my $file = readdir($dir)) {
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
            system("rm -rf $sitesDir/$file/enmlogs/*csv.gz");
          }
        }
      }
      closedir($dir) or die "Unable to close Sites Directory : $!";
    }
}
main();

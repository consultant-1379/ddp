#! /usr/bin/perl

#=====================================================================
### Script  : checkInstr.pl
### Author  : Sourav Pujara (xsoupuj)
### Purpose : We need to create a input configuration file with mbean and vm present in it.It will check whether mbeans are present or not in the VM. Current date is optional here.
###
### Usage   : perl checkInstr.pl --inputFile <inputFile> --site <site> --currentDate <CurrentDate>
### 
###=====================================================================
use strict;
use warnings;

use Getopt::Long;
use Data::Dumper;

sub main {
    my ($inputFile,$site,$currentDateDDMMYY);
    my $result = GetOptions (
        "inputFile=s" => \$inputFile,
	"site=s" => \$site,
        "currentDate=s" => \$currentDateDDMMYY
    );
    if(! defined $currentDateDDMMYY) {
      my $currentDate = `date +'%Y-%m-%d'`;
      $currentDate =~  /\d{2}(\d{2})-(\d+)-(\d+)/;
      $currentDateDDMMYY = "$3$2$1";
    }
    open (CONFIG, $inputFile) or die "Can't open file: $!";
    while (<CONFIG>) {
      my $data = $_;
      my($vm,$mbean) = split(",",$data);
      
      my @fileArray = `ls /data/stats/tor/$site/data/$currentDateDDMMYY/tor_servers/*$vm/$currentDateDDMMYY/instr.txt*`;
      my $file = $fileArray[0];
      if($file =~ /gz$/) {
        system("gunzip $file"); 
        @fileArray = `ls /data/stats/tor/$site/data/$currentDateDDMMYY/tor_servers/*$vm/$currentDateDDMMYY/instr.txt*`;
        $file = $fileArray[0];
      }
      chomp($file);
      open(FILE,$file) or die "Can't open file: $!";
      chomp($mbean);
      my $flag = 1;
      while(<FILE>) {
        if ($_ =~ /$mbean/) {
          $flag=0;
          last;
        }
      }
      if($flag == 1){
         print "$mbean not found in $file \n \n";
      }
      close(FILE)
    }
    close(CONFIG)
}

main

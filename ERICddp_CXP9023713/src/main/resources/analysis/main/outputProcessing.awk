#!/usr/local/bin/awk -f
#Author Andrew Bolster andrew.bolster@ericsson.com eboland

#This script summarises repeated dump warnings, and gunplot warnings 
#and errors, and additionally prepends all messages with one of 
# [HH:MM:SS] 
# [sitename] 
# [scriptname]
# [tardate]
# or nothing.

#For full output, outputProcessing.awk must be passed all relevent 
#variables (sitename, tardate, scriptname), and the format of the 
#output is decided by a 'format' variable, based on the characters T,S,t,and s.

# T = (T)imestamp
# S = (S)itename
# t = (t)ardate
# s = (s)criptname

# If BOTH the relevent character is present in the 'format' string 
#and the relevent variable has been passed and is non zero, the 
#output will be prepended with that value.

# If no format string is passed, the script assumed the format "TSts"
# [HH:MM:SS][sitename][tardate][scriptname]

#example 
#  $ echo -e "hello \n world" | awk -f outputProcessing.awk scriptname="SomeScript" format="TSts"
#  [SomeScript] hello
#  [SomeScript]  world                             
#  $ echo -e "hello \n world" | awk -f outputProcessing.awk tardate="300209" format="Tt" 
#  [13:47:19] [300209] hello
#  [13:47:20] [300209]  world			# newlines prepended with time and 
										# site independently 
										# (time exaggerated in this example)



BEGIN { 
      repeateddumps = 0
      ploterr = 0
      if ( ! format )
         format = "TSts"
}
function printline( input ) {
   line = ""
   split(format, formatarray, null)
   #this loop traverses the format sting in reverse, 
   #as the values are prepended to the existing line
   #so it all works out the way it should
   for(i = length(format); i>= 0 ; i--){
      cha = formatarray[i]
      if ( cha == "s" && scriptname ){
         line = ( "["scriptname"]" line )
      }else if ( cha == "t" && tardate ){
         line = ( "["tardate"]" line)
      }else if ( cha == "S" && sitename ){
         line = ( "["sitename"]" line )
      }else if ( cha == "T" ){
         line = ( "["strftime("%H:%M:%S")"]"  line )
      }
   }
   print line input
   
   #Unformatted (but cleaned up) print to /var/tmp/outputUnProcessed.log
   #print input >> "/var/tmp/outputUnProcessed.log";
   
}

#Regular Execution
{
   #count the number of repeateddumps and display that number instead of every warning
   if ( $0 ~ /WARN: Disgarding repeated dump for .*/ ) { repeateddumps++;}
   else if ( repeateddumps == 0 ) {
      #if its not a repeat it might be a gnuplot warning
      if ( $0 ~ /gnuplot> / ) { ploterr = 1; printline($0) }
      # if we're in the middle of a gnuplot error, continue until we 
	  # print an [[:alpha:]] line, then go back to normal
      else if ( ploterr == 1 )
      {   if ( $0 ~ /[[:alpha:]]/) { printline($0) ; ploterr = 0 }
         else {;}
      }
      else {
      # get rid of blank lines
         if ( $0 ~ /^$/ ) { ; }
         #and get rid of gnuplot warnings
         else if ($0 ~ /Warning: empty y range /) { ; }
         #print anything else
         else { printline($0) }
      }
   }
   #if repeats != 0 but this line is not a repeated dump then we must have 
   # finished, so print out the summary of repeated dumps
   else { message = "WARN: Disregarded " repeateddumps " repeated dumps";
      printline(message);
      repeateddumps = 0;
   }
}

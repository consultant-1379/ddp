/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
package net.atomique.ksar.Mac;

import java.util.StringTokenizer;
import javax.swing.tree.DefaultMutableTreeNode;
import net.atomique.ksar.diskName;
import net.atomique.ksar.kSar;
import org.jfree.data.general.SeriesException;
import org.jfree.data.time.Second;

/**
 *
 * @author alex
 */
public class Parser {

    public Parser(kSar hissar) {
        mysar = hissar;
    }

    public int parse(String thisLine, String first, StringTokenizer matcher) {
        int headerFound = 0;
        if (thisLine.indexOf("%usr") > 0) {
            headerFound = 1;
            mysar.underaverage = 0;
        }
        if (thisLine.indexOf("pgout/s") > 0) {
            headerFound = 1;
            mysar.underaverage = 0;
        }
        if (thisLine.indexOf("pgin/s") > 0) {
            headerFound = 1;
            mysar.underaverage = 0;
        }
        if (thisLine.indexOf("device") > 0) {
            headerFound = 1;
            mysar.underaverage = 0;
        }
        if (thisLine.indexOf("IFACE") > 0) {
            headerFound = 1;
            mysar.underaverage = 0;
        }
        if (thisLine.indexOf("New Disk: ") > 0) {
            return 1;
        }
        //if ( thisLine.indexOf("%usr") >0 ) { headerFound=1; underaverage=0;}

        String[] sarTime = first.split(":");
        if (sarTime.length != 3) {
            return 1;
        } else {
            if (sarTime.length == 3) {
                heure = Integer.parseInt(sarTime[0]);
                minute = Integer.parseInt(sarTime[1]);
                seconde = Integer.parseInt(sarTime[2]);
                now = new Second(seconde, minute, heure, mysar.day, mysar.month, mysar.year);
                if (mysar.statstart == null) {
                    mysar.statstart = new String(now.toString());
                    mysar.startofgraph = now;
                }
                if ( ! mysar.datefound.contains(now) ) {
                    mysar.datefound.add(now);
                }
                if (now.compareTo(mysar.lastever) > 0) {
                    mysar.lastever = now;
                    mysar.statend = new String(mysar.lastever.toString());
                    mysar.endofgraph = now;
                }
            }
        }

        lastHeader = matcher.nextToken();
        // was a header ?
        if (headerFound == 1) {
            if (lastHeader.equals(statType)) {
                headerFound = 0;
                return 1;
            }

            statType = lastHeader;

            if (lastHeader.equals("%usr")) {
                if (sarCPU4 == null) {
                    sarCPU4 = new cpuSar(mysar);
                    if ( mysar.myUI != null ) {
                        sarCPU4.addtotree(mysar.graphtree);
                    }
                    mysar.pdfList.put("MaccpuSar", sarCPU4);
                    sarCPU4.setGraphLink("MaccpuSar");
                }
                return 1;
            }
            if (lastHeader.equals("pgout/s")) {
                if (sarPGOUT4 == null) {
                    sarPGOUT4 = new pgoutSar(mysar);
                    if ( mysar.myUI != null ) {
                        sarPGOUT4.addtotree(mysar.graphtree);
                    }
                    mysar.pdfList.put("MacpgoutSar", sarPGOUT4);
                    sarPGOUT4.setGraphLink("MacpgoutSar");
                }
                return 1;
            }
            if (lastHeader.equals("pgin/s")) {
                if (sarPGIN4 == null) {
                    sarPGIN4 = new pginSar(mysar);
                    if ( mysar.myUI != null ) {
                        sarPGIN4.addtotree(mysar.graphtree);
                    }
                    mysar.pdfList.put("MacpginSar", sarPGIN4);
                    sarPGIN4.setGraphLink("MacpginSar");
                }
                return 1;
            }

            if (statType.equals("device")) {
                if ( ! mysar.hasdisknode ) {
                    if ( mysar.myUI != null ) {
                        mysar.add2tree(mysar.graphtree,mysar.diskstreenode);
                    }
                    mysar.hasdisknode = true;
                }
                return 1;
            }

            if (statType.equals("IFACE")) {
                if (thisLine.indexOf("Ipkts/s") > 0) {
                    statType = "Ipkts/s";
                }
                if (thisLine.indexOf("Ierrs/s") > 0) {
                    statType = "Ierrs/s";
                }
                if ( ! mysar.hasifnode ) {
                    if ( mysar.myUI != null ) {
                        mysar.add2tree(mysar.graphtree,mysar.ifacetreenode);
                    }
                    mysar.hasifnode = true;
                }
                return 1;
            }

            headerFound = 0;
            return 1;
        }

        try {
            if (statType.equals("%usr")) {
                val1 = new Float(lastHeader);
                val2 = new Float(matcher.nextToken());
                val3 = new Float(matcher.nextToken());
                sarCPU4.add(now, val1, val2, val3);
                return 1;
            }
            if (statType.equals("pgout/s")) {
                val1 = new Float(lastHeader);
                sarPGOUT4.add(now, val1);
                return 1;
            }
            if (statType.equals("pgin/s")) {
                val1 = new Float(lastHeader);
                val2 = new Float(matcher.nextToken());
                val3 = new Float(matcher.nextToken());
                sarPGIN4.add(now, val1, val2, val3);
                return 1;
            }
            if (statType.equals("device")) {
                if (mysar.underaverage == 1) {
                    return 1;
                }
                blockSar mysarblock;
                if (!mysar.disksSarList.containsKey(lastHeader + "-t1")) {
                    diskName tmp = new diskName(lastHeader);
                    mysar.AlternateDiskName.put(lastHeader, tmp);
                    tmp.setTitle((String) mysar.Adiskname.get(lastHeader));
                    mysarblock = new blockSar(mysar, lastHeader);
                    mysar.disksSarList.put(lastHeader + "-t1", mysarblock);
                    mysarblock.setGraphLink(lastHeader + "-t1");
                    mysar.pdfList.put(lastHeader + "-t1", mysarblock);
                    mysarblock.addtotree(mysar.diskstreenode);
                } else {
                    mysarblock = (blockSar) mysar.disksSarList.get(lastHeader + "-t1");
                }
                val1 = new Float(matcher.nextToken());
                val2 = new Float(matcher.nextToken());
                mysarblock.add(now, val1, val2);
                return 1;
            }
            if (statType.equals("Ipkts/s")) { //DEV		tps	 rd_sec/s  wr_sec/s	 avgrq-sz	 avgqu-sz	  await		svctm	  %util
                if (mysar.underaverage == 1) {
                    return 1;
                }
                iface1Sar mysarif1;
                iface2Sar mysarif2;
                if (!mysar.ifaceSarList.containsKey(lastHeader + "-if1")) {
                    mysarif1 = new iface1Sar(mysar, lastHeader);
                    mysarif2 = new iface2Sar(mysar, lastHeader);
                    DefaultMutableTreeNode myif = new DefaultMutableTreeNode(lastHeader);
                    mysarif1.addtotree(myif);
                    mysarif2.addtotree(myif);
                    mysar.ifaceSarList.put(lastHeader + "-if1", mysarif1);
                    mysar.ifaceSarList.put(lastHeader + "-if2", mysarif2);
                    mysarif1.setGraphLink(lastHeader + "-if1");
                    mysarif2.setGraphLink(lastHeader + "-if2");
                    mysar.pdfList.put(lastHeader + "-if1", mysarif1);
                    mysar.pdfList.put(lastHeader + "-if2", mysarif2);
                    mysar.add2tree(mysar.ifacetreenode,myif);
                } else {
                    mysarif1 = (iface1Sar) mysar.ifaceSarList.get(lastHeader + "-if1");
                    mysarif2 = (iface2Sar) mysar.ifaceSarList.get(lastHeader + "-if2");
                }
                val1 = new Float(matcher.nextToken());
                val2 = new Float(matcher.nextToken());
                val3 = new Float(matcher.nextToken());
                val4 = new Float(matcher.nextToken());
                mysarif1.add(now, val1, val2, val3, val4);
                return 1;
            }
            if (statType.equals("Ierrs/s")) {
                if (mysar.underaverage == 1) {
                    return 1;
                }
                iface1Sar mysarif1;
                iface2Sar mysarif2;
                if (!mysar.ifaceSarList.containsKey(lastHeader + "-if1")) {
                    mysarif1 = new iface1Sar(mysar, lastHeader);
                    mysarif2 = new iface2Sar(mysar, lastHeader);
                    DefaultMutableTreeNode myif = new DefaultMutableTreeNode(lastHeader);
                    mysarif1.addtotree(myif);
                    mysarif2.addtotree(myif);
                    mysar.ifaceSarList.put(lastHeader + "-if1", mysarif1);
                    mysar.ifaceSarList.put(lastHeader + "-if2", mysarif2);
                    mysar.pdfList.put(lastHeader + "-if1", mysarif1);
                    mysar.pdfList.put(lastHeader + "-if2", mysarif2);
                    mysarif1.setGraphLink(lastHeader + "-if1");
                    mysarif2.setGraphLink(lastHeader + "-if2");
                    mysar.add2tree(mysar.ifacetreenode,myif);
                } else {
                    mysarif1 = (iface1Sar) mysar.ifaceSarList.get(lastHeader + "-if1");
                    mysarif2 = (iface2Sar) mysar.ifaceSarList.get(lastHeader + "-if2");
                }
                val1 = new Float(matcher.nextToken());
                val2 = new Float(matcher.nextToken());
                val3 = new Float(matcher.nextToken());
                val4 = new Float(matcher.nextToken());
                mysarif2.add(now, val1, val2, val3, val4);
                return 1;
            }
        } catch (SeriesException e) {
            System.out.println("Mac parser: " + e);
            return -1;
        }
        return 0;
    }
    kSar mysar;
    Float val1;
    Float val2;
    Float val3;
    Float val4;
    Float val5;
    Float val6;
    Float val7;
    Float val8;
    Float val9;
    Float val10;
    Float val11;
    int heure = 0;
    int minute = 0;
    int seconde = 0;
    Second now = new Second(0, 0, 0, 1, 1, 1970);
    String lastHeader;
    String statType = "none";
    cpuSar sarCPU4 = null;
    pgoutSar sarPGOUT4 = null;
    pginSar sarPGIN4 = null;

}

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
package net.atomique.ksar;

import java.text.DecimalFormat;
import java.text.FieldPosition;
import java.text.NumberFormat;
import java.text.ParsePosition;

/**
 *
 * @author alex
 */
public class IEEE1541Number extends NumberFormat {

    private static final long serialVersionUID = 5L;

    public IEEE1541Number() {
    }

    public IEEE1541Number(int value) {
        kilo = value;
    }

    /*
     * EEICJON: Allow the use of a multiplication factor to convert from one format to another
     */
    public IEEE1541Number(int value, double mult) {
        kilo = value;
        multiple = mult;
    }

    public IEEE1541Number(double mult) {
        multiple = mult;
    }

    public StringBuffer format(double number, StringBuffer toAppendTo, FieldPosition pos) {
        number = number * multiple;
        if (kilo == 0) {
            return toAppendTo.append(number);
        }
        if ((number * kilo) < 1024) {
            return toAppendTo.append(number);
        }
        if ((number * kilo) < (1024 * 1024)) {
            DecimalFormat formatter = new DecimalFormat("#,##0.0");
            toAppendTo.append(formatter.format((double) number / 1024.0)).append(" KB");
            return toAppendTo;
        }
        if ((number * kilo) < (1024 * 1024 * 1024)) {
            DecimalFormat formatter = new DecimalFormat("#,##0.0");
            toAppendTo.append(formatter.format((double) (number * kilo) / (1024.0 * 1024.0))).append(" MB");
            return toAppendTo;
        }

        DecimalFormat formatter = new DecimalFormat("#,##0.0");
        toAppendTo.append(formatter.format((double) (number * kilo) / (1024.0 * 1024.0 * 1024.0))).append(" GB");
        return toAppendTo;
    }

    public StringBuffer format(long number, StringBuffer toAppendTo, FieldPosition pos) {
        return format((double) (number * kilo), toAppendTo, pos);
    }

    public Number parse(String source, ParsePosition parsePosition) {
        return null;
    }
    int kilo = 0;
    double multiple = 1.0;
}

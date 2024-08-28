import org.jfree.chart.ChartFactory;
import org.jfree.chart.JFreeChart;
import org.jfree.chart.StandardChartTheme;
import org.jfree.chart.axis.CategoryAxis;
import org.jfree.chart.axis.DateAxis;
import org.jfree.chart.axis.NumberAxis;
import org.jfree.chart.labels.StandardXYToolTipGenerator;
import org.jfree.chart.labels.XYToolTipGenerator;
import org.jfree.chart.plot.CombinedDomainXYPlot;
import org.jfree.chart.plot.PiePlot;
import org.jfree.chart.plot.PlotOrientation;
import org.jfree.chart.plot.XYPlot;
import org.jfree.chart.renderer.xy.StackedXYBarRenderer;
import org.jfree.chart.renderer.xy.XYBarRenderer;
import org.jfree.chart.renderer.xy.XYItemRenderer;
import org.jfree.chart.renderer.xy.XYLineAndShapeRenderer;
import org.jfree.data.category.CategoryDataset;
import org.jfree.data.category.CategoryToPieDataset;
import org.jfree.data.category.DefaultCategoryDataset;
import org.jfree.data.general.AbstractDataset;
import org.jfree.data.time.*;
import org.jfree.data.xy.XYDataset;

import java.awt.*;
import java.awt.geom.GeneralPath;
import java.io.*;
import java.net.URL;
import java.text.NumberFormat;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;

import static org.jfree.chart.axis.CategoryLabelPositions.UP_90;


public class PlotFactory {
    private SimpleDateFormat m_DF;
    private Class<?> m_timeClass;
    private int m_timeType;
    private int m_timeInterval;
    private LineNumberReader m_In;

    public PlotFactory(final String data) throws Exception {
        openDataStream(data);
    }

    public JFreeChart makeChart() throws Exception {
        final Map<String, Object> data = new HashMap<String, Object>();

        JFreeChart chart = null;
        String line;
        //
        // Valid commands are either (tsc,tpvc,tt) followed by a plot command
        // The "t" commands are uses to specify a time series data format
        //  tsc  -> Time Series Collection: used for standard XY line plots
        //  tpvc -> Time Period Values Collection: used for bar plots
        //          where the bars have variable widths
        //  tt   -> Time Table XYDataset: use for bar plots where the bars
        //          have the same width
        //
        //  plot specifies the type of plot to be drawn
        while ((line = m_In.readLine()) != null) {
            //
            // Time Series Commands
            //
            if (line.startsWith("tsc")) {
                TimeSeriesCollection tsc = (TimeSeriesCollection) data.get("tsc");
                if (tsc == null) {
                    tsc = new TimeSeriesCollection();
                    data.put("tsc", tsc);
                }
                readTSC(line, tsc);

            } else if (line.startsWith("tpvc")) { // bar(column) data
                data.put("tpvc", readTPVC(line));
            } else if (line.startsWith("tt")) { // TimeTableXYDataset
                TimeTableXYDataset tt = (TimeTableXYDataset) data.get("tt");
                if (tt == null) {
                    tt = new TimeTableXYDataset();
                    data.put("tt", tt);
                }
                readTT(line, tt);
            } else if (line.startsWith("cat")) {
                DefaultCategoryDataset dataset = (DefaultCategoryDataset) data.get("cat");
                if (dataset == null) {
                    dataset = new DefaultCategoryDataset();
                    data.put("cat", dataset);
                }
                readCat(line, dataset);
            }
            //
            // Plot Command
            //
            else if (line.startsWith("plot")) {
                chart = plot(line, data);
            } else if (line.trim().length() > 0) {
                System.out.println("WARN: Unknown command \"" + line + "\"");
            }
        }
        m_In.close();
        return chart;
    }

    private JFreeChart plot(final String line, final Map<String, Object> data) throws Exception {
        //
        // plot;<type>;<title>;<x-axis label>;<y-axis label>;<forcelegend>
        //
        final String[] args = line.split(";");
        final String type = args[1];
        String title = "";
        if (args.length > 2) {
            title = args[2];
        }
        String xlabel = null;
        if (args.length > 3 && (!"Time".equals(args[3]))) {
            xlabel = args[3];
        }
        String ylabel = "";
        if (args.length > 4) {
            ylabel = args[4];
        }
        boolean forcelegend = false;
        if (args.length > 5) {
            forcelegend = Boolean.parseBoolean(args[5]);
        }

        JFreeChart chart = null;

        // Get rid of the shadow stuff
        ChartFactory.setChartTheme(StandardChartTheme.createLegacyTheme());

        if (type.equals("tsc")) {
            // Standard line plot
            final TimeSeriesCollection tsc = (TimeSeriesCollection) getDataSet(data, "tsc");
            boolean legend = true;
            if (tsc.getSeriesCount() == 1 && !forcelegend) {
                legend = false;
            }
            chart =
                    ChartFactory.createTimeSeriesChart(title, xlabel, ylabel,
                            tsc, legend, true, false);
            final XYPlot xyplot = chart.getXYPlot();
            turnOnCrosshairs(xyplot);
        } else if (type.equals("tb")) {
            org.jfree.chart.renderer.xy.XYBarRenderer.setDefaultShadowsVisible(false);

            // Bar plot
            chart
                    = ChartFactory.createXYBarChart(title, xlabel, true, ylabel,
                    (TimePeriodValuesCollection) getDataSet(data, "tpvc"),
                    PlotOrientation.VERTICAL, forcelegend, true, false);
        } else if (type.equals("tbl")) {
            // Combined plot used by SDM Loading
            final TimePeriodValuesCollection tpvc = (TimePeriodValuesCollection) getDataSet(data, "tpvc");
            final XYPlot xyplot = new XYPlot(tpvc,
                    new DateAxis(xlabel),
                    new NumberAxis(ylabel),
                    new XYBarRenderer());
            final TimePeriodValuesCollection tpvc1 = makeDuration(tpvc);
            final XYPlot xyplot_2_
                    = new XYPlot(tpvc1,
                    new DateAxis(xlabel),
                    new NumberAxis("Duration"),
                    new XYBarRenderer());
            final CombinedDomainXYPlot plot
                    = new CombinedDomainXYPlot(new DateAxis(xlabel));
            plot.add(xyplot);
            plot.add(xyplot_2_);
            chart = new JFreeChart(title,
                    JFreeChart.DEFAULT_TITLE_FONT,
                    plot, forcelegend);
        } else if (type.equals("sb")) {
            // Stacked bar chart
            // Used by the log pages
            final TimeTableXYDataset tt = (TimeTableXYDataset) getDataSet(data, "tt");
            boolean legend = true;
            if (tt.getSeriesCount() == 1 && !forcelegend) {
                legend = false;
            }
            org.jfree.chart.renderer.xy.XYBarRenderer.setDefaultShadowsVisible(false);
            final XYPlot xyplot = new XYPlot(tt,
                    new DateAxis(xlabel),
                    new NumberAxis(ylabel),
                    new StackedXYBarRenderer());
            turnOnCrosshairs(xyplot);

            chart = new JFreeChart(title, xyplot);
            if (!legend) {
                chart.removeLegend();
            }
        } else if (type.equals("xy")) {
            // Dot/point plots
            // Use to draw the connection/disconnect graphs
            final XYLineAndShapeRenderer rend = new XYLineAndShapeRenderer(false, true);
            final XYPlot xyplot = new XYPlot((TimeSeriesCollection) getDataSet(data, "tsc"),
                    new DateAxis(xlabel),
                    new NumberAxis(ylabel),
                    rend);

            turnOnCrosshairs(xyplot);

            final float step = 2;
            for (int i = 6; i < args.length; i++) {
                final int pointType = Integer.parseInt(args[i]);
                Shape sp = null;
                switch (pointType) {
                    case 1: {
                        final GeneralPath gp = new GeneralPath();
                        gp.moveTo(0, step);
                        gp.lineTo(0, -step);
                        gp.moveTo(step, 0);
                        gp.lineTo(-step, 0);
                        sp = gp;
                        break;
                    }

                    case 2: {
                        final GeneralPath gp = new GeneralPath();
                        gp.moveTo(-step, -step);
                        gp.lineTo(step, step);
                        gp.moveTo(-step, step);
                        gp.lineTo(step, -step);
                        sp = gp;
                        break;
                    }
                }

                if (sp != null) {
                    rend.setSeriesShape(i - 5, sp);
                }
            }

            chart = new JFreeChart(title, xyplot);
        } else if (type.equals("sa")) {
            chart =
                    ChartFactory.createStackedXYAreaChart(
                            title,                    // chart title
                            xlabel,                   // domain axis label
                            ylabel,                   // range axis label
                            (TimeTableXYDataset) getDataSet(data, "tt"), // data
                            PlotOrientation.VERTICAL, // the plot orientation
                            true,                     // legend
                            true,                     // tooltips
                            false                     // urls
                    );
            final XYPlot plot = (XYPlot) chart.getXYPlot();

            final DateAxis dateAxis = new DateAxis(xlabel);
            dateAxis.setLowerMargin(0.0);
            dateAxis.setUpperMargin(0.0);
            plot.setDomainAxis(dateAxis);

            setTimeToolTip(plot);

            turnOnCrosshairs(plot);
        } else if (type.equals("cat")) {
            org.jfree.chart.renderer.category.StackedBarRenderer.setDefaultShadowsVisible(false);
            chart = ChartFactory.createStackedBarChart(title,
                    xlabel,
                    ylabel,
                    (CategoryDataset) getDataSet(data, "cat"),
                    PlotOrientation.VERTICAL,
                    true,
                    true,
                    false);

            CategoryAxis axis = chart.getCategoryPlot().getDomainAxis();
            axis.setCategoryLabelPositions(UP_90);
        } else if (type.equals("pie")) {
            CategoryToPieDataset pieData =
                    new CategoryToPieDataset((CategoryDataset) getDataSet(data, "cat"),
                            org.jfree.util.TableOrder.BY_ROW, 0);
            chart = ChartFactory.createPieChart(title,
                    pieData,
                    true,
                    true,
                    false);
            ((PiePlot) chart.getPlot()).setLabelGenerator(null);
        }

        return chart;
    }


    private void setTimeToolTip(final XYPlot plot) {
        final XYItemRenderer renderer = plot.getRenderer();

        final XYDataset ds = plot.getDataset();
        if (ds.getSeriesCount() == 1) {
            final XYToolTipGenerator generator =
                    new StandardXYToolTipGenerator(
                            "{1}, {2}",
                            new SimpleDateFormat("dd-MM-yy HH:mm:ss"),
                            NumberFormat.getInstance()
                    );
            renderer.setToolTipGenerator(generator);
        } else {
            for (int seriesIndex = 0; seriesIndex < ds.getSeriesCount(); seriesIndex++) {
                final XYToolTipGenerator generator =
                        new StandardXYToolTipGenerator(
                                ds.getSeriesKey(seriesIndex) + ": ({1}, {2})",
                                new SimpleDateFormat("dd-MM-yy HH:mm:ss"),
                                NumberFormat.getInstance()
                        );
                renderer.setSeriesToolTipGenerator(seriesIndex, generator);
            }
        }
    }

    private void turnOnCrosshairs(final XYPlot plot) {
        plot.setDomainCrosshairVisible(true);
        plot.setDomainCrosshairLockedOnData(true);
        plot.setRangeCrosshairVisible(true);
        plot.setRangeCrosshairLockedOnData(true);
    }

    private TimePeriodValuesCollection makeDuration(final TimePeriodValuesCollection tpvc)
            throws Exception {
        final TimePeriodValues srcTpv = tpvc.getSeries(0);
        final TimePeriodValues tpvDur = new TimePeriodValues("");
        for (int i = 0; i < srcTpv.getItemCount(); i++) {
            final TimePeriodValue srcTp = srcTpv.getDataItem(i);

            final TimePeriodValue tpDur = (TimePeriodValue) srcTp.clone();
            final SimpleTimePeriod stp = (SimpleTimePeriod) tpDur.getPeriod();

            final long duration = (stp.getEnd().getTime() - stp.getStart().getTime());
            tpDur.setValue(new Integer((int) (duration / 1000L)));
            tpvDur.add(tpDur);
        }

        return new TimePeriodValuesCollection(tpvDur);
    }

    // Read a TimePeriodValuesCollection
    private TimePeriodValuesCollection readTPVC(String line) throws Exception {
        // Format of line is
        // startdate value peroidDuration
        final SimpleDateFormat df = new SimpleDateFormat("yyyy-MM-dd:HH:mm:ss");
        final TimePeriodValues tpv = new TimePeriodValues("");

        while ((line = m_In.readLine()) != null && line.length() != 0) {
            final String[] parts = line.split(" ");
            final Date startDate = df.parse(parts[0]);
            final double value = Double.parseDouble(parts[1]);
            final long duration = Long.parseLong(parts[2]);
            final Date endDate = new Date(startDate.getTime() + duration);
            tpv.add(new SimpleTimePeriod(startDate, endDate), value);
        }
        return new TimePeriodValuesCollection(tpv);
    }

    private void readCat(String header, DefaultCategoryDataset dataset) throws NumberFormatException, IOException {
        final String[] fields = header.split(";");

        String line;
        while ((line = m_In.readLine()) != null && line.length() != 0) {
            final String[] parts = line.split(" ");
            String cat = parts[0];
            for (int index = 1; index < parts.length; index++) {
                String series = fields[index];
                Double value = Double.parseDouble(parts[index]);
                dataset.addValue(value, series, cat);
            }
        }
    }

    private void readTSC(final String header, final TimeSeriesCollection tsc) throws Exception {
        final String[] fields = header.split(";");
        initTime(fields[1]);

        final TimeSeries[] tsList = new TimeSeries[fields.length - 2];
        for (int i = 2; i < fields.length; i++) {
            tsList[i - 2] = new TimeSeries(fields[i], m_timeClass);
        }

        String line;
        while ((line = m_In.readLine()) != null && line.length() != 0) {
            final String[] parts = line.split(" ");
            final RegularTimePeriod rtp = (RegularTimePeriod) getTime(parts[0]);
            for (int i = 0; i < tsList.length; i++) {
                if (!parts[i + 1].equals("-")) {
                    tsList[i].addOrUpdate(rtp, Double.parseDouble(parts[i + 1]));
                }
            }
        }

        //TimeSeriesCollection tsc = new TimeSeriesCollection();
        //tsc.setDomainIsPointsInTime(false);
        for (int i = 0; i < tsList.length; i++) {
            tsc.addSeries(tsList[i]);
        }
    }

    private void readTT(final String header, final TimeTableXYDataset tt) throws Exception {
        //tt;<type peroid type>;<series name>[;<series name>...]
        final String[] fields = header.split(";");
        initTime(fields[1]);

        String line;
        while ((line = m_In.readLine()) != null && line.length() != 0) {
            //time <series 1 value> [<series 2 value> ...]
            final String[] parts = line.split(" ");
            final TimePeriod tp = getTime(parts[0]);

            for (int i = 2; i < fields.length; i++) {
                tt.add(tp,
                        Double.parseDouble(parts[i - 1]),
                        fields[i]);
            }
        }
    }

    private TimePeriod getTime(final String timeStr) throws Exception {
        final Date date = m_DF.parse(timeStr);

        switch (m_timeType) {
            case 1:
                return new Day(date);

            case 2:
                return new Minute(date);

            case 3:
                return new Second(date);

            case 4:
                return new Millisecond(date);

            default:
                return new SimpleTimePeriod(date.getTime(), date.getTime() + ((m_timeInterval - 1) * 1000));
        }
    }

    private void initTime(final String timeTypeStr) throws Exception {
        if (timeTypeStr.equals("day")) {
            m_timeType = 1;
        } else if (timeTypeStr.equals("minute")) {
            m_timeType = 2;
        } else if (timeTypeStr.equals("sec") || timeTypeStr.equals("second")) {
            m_timeType = 3;
        } else if (timeTypeStr.equals("ms")) {
            m_timeType = 4;
        } else {
            m_timeType = 5;
            m_timeInterval = Integer.parseInt(timeTypeStr);
        }

        switch (m_timeType) {
            case 1:
                m_DF = new SimpleDateFormat("yyyy-MM-dd");
                m_timeClass = Day.class;
                break;

            case 2:
                m_DF = new SimpleDateFormat("yyyy-MM-dd:HH:mm");
                m_timeClass = Minute.class;
                break;

            case 3:
                m_DF = new SimpleDateFormat("yyyy-MM-dd:HH:mm:ss");
                m_timeClass = Second.class;
                break;

            case 4:
                m_DF = new SimpleDateFormat("yyyy-MM-dd:HH:mm:ss.SSS");
                m_timeClass = Millisecond.class;
                break;

            case 5:
                m_DF = new SimpleDateFormat("yyyy-MM-dd:HH:mm:ss");
                m_timeClass = SimpleTimePeriod.class;
                break;
        }
        ;
    }

    private void openDataStream(final String data) throws Exception {
        Reader dataReader = null;
        if (data.startsWith("http")) {
            final URL url = new URL(data);
            dataReader = new InputStreamReader(url.openStream());
        } else {
            dataReader = new FileReader(data);
        }

        m_In = new LineNumberReader(dataReader);
    }

    private AbstractDataset getDataSet(final Map<String, Object> data, String key) {
        AbstractDataset result = (AbstractDataset) data.get(key);
        if (result == null) {
            if (key == "") {
                result = new TimeSeriesCollection();
            } else if (key == "tpvc") {
                result = new TimePeriodValuesCollection();
            } else if (key == "tt") {
                result = new TimeTableXYDataset();
            } else if (key == "cat") {
                result = new DefaultCategoryDataset();
            }
        }
        return result;
    }
}

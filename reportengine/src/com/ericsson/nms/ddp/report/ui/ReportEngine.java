package com.ericsson.nms.ddp.report.ui;

import java.awt.Color;
import java.io.IOException;
import java.io.OutputStream;
import java.io.PrintWriter;
import java.text.SimpleDateFormat;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;

import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.apache.poi.hssf.usermodel.HSSFWorkbook;
import org.apache.poi.ss.usermodel.Cell;
import org.apache.poi.ss.usermodel.CellStyle;
import org.apache.poi.ss.usermodel.Font;
import org.apache.poi.ss.usermodel.IndexedColors;
import org.apache.poi.ss.usermodel.Row;
import org.apache.poi.ss.usermodel.Sheet;
import org.apache.poi.ss.usermodel.Workbook;
import org.apache.poi.ss.util.WorkbookUtil;
import org.jfree.chart.ChartFactory;
import org.jfree.chart.ChartUtilities;
import org.jfree.chart.JFreeChart;
import org.jfree.chart.axis.DateAxis;
import org.jfree.chart.plot.PlotOrientation;

import com.ericsson.nms.ddp.report.data.DDPTimeSeries;
import com.ericsson.nms.ddp.report.data.Report;
import com.ericsson.nms.ddp.report.db.DBHandle;

/**
 * Servlet implementation class Report
 */
@WebServlet("/rg")
public class ReportEngine extends HttpServlet {

	/**
	 * 
	 */
	private static final long serialVersionUID = 1L;
	
	/**
	 * Retrieve either a PNG image for a specific timeseries or an excel spreadsheet.
	 */
	protected void doGet(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {		
		Report report = null;

		if (request.getParameter("id") != null) {
			DBHandle hdl = new DBHandle();
			try {
				int tplId = Integer.parseInt(request.getParameter("id"));
				report = hdl.getReport(tplId, request.getParameterMap());
			} catch (NumberFormatException e) {
				report = new Report("Invalid template ID: " + request.getParameter("id"),
						"Please verify the data you provided and regenerate this report", request.getParameterMap());
			}
			
		}
		if (report == null)
			report = new Report("Could not generate report for ID " + request.getParameter("id"),
				"Please check supplied parameters and try again", request.getParameterMap());
		
		String format = request.getParameter("format");
		if (format == null) {
			// TODO: Generate a PNG?
			response.getWriter().println("No parameters passed");
		} else if (format.equals("png")) {
			// We're only doing one timeseries chart 
			String tsName = request.getParameter("ts");
			if (request.getParameter("debug") != null) {
				response.getWriter().println(tsName + "\n");
				DDPTimeSeries ts = report.getTimeSeries(tsName);
				if (ts == null) {
					response.getWriter().println("No such TimeSeries: " + tsName + "\n");
					response.getWriter().println("Report: " + report.getName() + "\nDescription: " + report.getDescription() + "\n");
					for (Iterator<String> iterator = report.getTimeSeriesMap().keySet().iterator() ; iterator.hasNext() ; ) {
						response.getWriter().println("\t" + iterator.next() + "\n");
					}
				} else {
					response.getWriter().println(ts.getSql());
					for (Iterator<String> it = ts.getDataTable().keySet().iterator(); it.hasNext() ; ) {
						response.getWriter().println(it.next());
					}
				}
			} else { 
				response.setContentType("image/png");
				doChart(report.getTimeSeries(tsName), response.getOutputStream());
			}
		} else if (format.equals("xls")) {
			response.setContentType("application/vnd.ms-excel");
			doExcelDoc(report, response.getOutputStream());
		}
	}

	protected void doPost(HttpServletRequest request, HttpServletResponse response)
			throws ServletException, IOException {
		doGet(request, response);
	}
	
	public static void println(String txt) {
		System.out.println(txt);
	}
	
	private void doChart(DDPTimeSeries ts, OutputStream out) {
		JFreeChart chart;
		if (ts == null) {
			ReportEngine.println("Null TS");
			return;
		} else if (ts.isStacked()) {
			chart = ChartFactory.createStackedXYAreaChart(ts.getTitle(), null, null,
					ts.getTimeTableXYDataset() , PlotOrientation.VERTICAL, true, false, false);
			chart.getXYPlot().setDomainAxis(new DateAxis());
		} else {
			chart = ChartFactory.createTimeSeriesChart(ts.getTitle(),
					null, null, ts.getTimeSeriesCollection() , true, false, false);
		}
		chart.getPlot().setBackgroundPaint(Color.white);
		chart.getXYPlot().setDomainGridlinePaint(Color.gray);
		chart.getXYPlot().setRangeGridlinePaint(Color.gray);
		try {
			ChartUtilities.writeBufferedImageAsPNG(out, chart.createBufferedImage(600, 400));
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	
	private void doExcelDoc(Report report, OutputStream out) {
		Workbook wb = new HSSFWorkbook();
		Map<String, CellStyle> styles = createStyles(wb);
		
		SimpleDateFormat df = new SimpleDateFormat("dd-MM-yy HH:mm:ss");
		
		// Overview worksheet
		Map<String,String> overviewData = report.getOverview();
		Sheet overviewSheet = wb.createSheet("Overview");
		int rowIdx = 1;
		for (Iterator<String> k = overviewData.keySet().iterator() ; k.hasNext() ; ) {
			String key = k.next();
			String lbl = key;
			if (key.equals("id") || key.equals("submitexcel") || key.equals("format")) continue;
			if (key.equals("start_time")) lbl = "Start Time";
			if (key.equals("end_time")) lbl = "End Time";
			
			Row ovRow = overviewSheet.createRow(rowIdx++);
			Cell descCell = ovRow.createCell(0);
			descCell.setCellStyle(styles.get("header"));
			descCell.setCellValue(lbl);
			descCell.getCellStyle().setAlignment(CellStyle.VERTICAL_TOP);
			
			Cell c = ovRow.createCell(1);
			c.setCellValue(overviewData.get(key));
			c.getCellStyle().setWrapText(true);
			c.getCellStyle().setAlignment(CellStyle.VERTICAL_TOP);
		}
		Row ovRow = overviewSheet.createRow(rowIdx++);
		Cell descCell = ovRow.createCell(0);
		descCell.setCellStyle(styles.get("header"));
		descCell.setCellValue("Creation Time");
		descCell.getCellStyle().setAlignment(CellStyle.VERTICAL_TOP);
		
		Cell valCell = ovRow.createCell(1);
		valCell.setCellValue(df.format(new java.util.Date(System.currentTimeMillis())));
		valCell.getCellStyle().setWrapText(true);
		valCell.getCellStyle().setAlignment(CellStyle.VERTICAL_TOP);
		
		overviewSheet.setColumnWidth(0, 256 * 20);
		overviewSheet.setColumnWidth(1, 256 * 40);
		
		for (Iterator<String> i = report.getTimeSeriesMap().keySet().iterator() ; i.hasNext() ; ) {
			String n = i.next();
			DDPTimeSeries ddpts = report.getTimeSeries(n);
			// sheet names cannot contain certain characters and must be < 31 characters long
			Sheet sheet = wb.createSheet(WorkbookUtil.createSafeSheetName(ddpts.getTitle()));

	        Map<String, List<String>> data = ddpts.getDataTable();
	        // Columns
	        int colIndex = 0;
	        Row headerRow = sheet.createRow(0);
	        // times
	        Cell c = headerRow.createCell(colIndex);
	        c.setCellValue(ddpts.getTimeCol());
	        c.setCellStyle(styles.get("header"));
        	sheet.autoSizeColumn(colIndex);	        
	        colIndex++;
	        for (Iterator<String> j = data.keySet().iterator() ; j.hasNext() ; ) {
	        	String name = j.next();
	        	if (name.equals(ddpts.getTimeCol())) continue;
	        	c = headerRow.createCell(colIndex);
	        	c.setCellValue(name);
	        	c.setCellStyle(styles.get("header"));
	        	sheet.autoSizeColumn(colIndex);
	        	colIndex++;
	        }
	    	// count the number of entries in the time column
	    	int nRows = data.get(ddpts.getTimeCol()).size();
	    	for (int rowIndex = 1 ; rowIndex < nRows + 1 ; rowIndex++) {
	    		Row thisRow = sheet.createRow(rowIndex);
	    		colIndex = 0;
	    		c = thisRow.createCell(colIndex);
	    		c.setCellValue(data.get(ddpts.getTimeCol()).get(rowIndex - 1));
	    		colIndex++;
	    		for (Iterator<String> nameIter = data.keySet().iterator() ; nameIter.hasNext() ; ) {
	    			String name = nameIter.next();
	    			if (name.equals(ddpts.getTimeCol())) continue;
	    			c = thisRow.createCell(colIndex);
	    			if (data != null && data.get(name) != null && data.get(name).get(rowIndex - 1) != null) {
	    				try {
	    					c.setCellValue(Double.parseDouble(data.get(name).get(rowIndex - 1)));
	    				} catch (NumberFormatException e) {
	    					c.setCellValue(data.get(name).get(rowIndex - 1));
	    				}
	    			}
	    			colIndex++;
	    		}
	    	}
	    	for (int colCount = 0 ; colCount <= data.keySet().size() ; colCount++) {
	    		// widths in 1/256th of a character
	    		sheet.setColumnWidth(colCount, 256 * 20);
	    	}
		}

		try {
			wb.write(out);
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	
    /**
     * Create a library of cell styles
     */
    private Map<String, CellStyle> createStyles(Workbook wb){
        Map<String, CellStyle> styles = new HashMap<String, CellStyle>();
        CellStyle style;
        Font titleFont = wb.createFont();
        titleFont.setFontHeightInPoints((short)16);
        titleFont.setBoldweight(Font.BOLDWEIGHT_BOLD);
        style = wb.createCellStyle();
        style.setAlignment(CellStyle.ALIGN_CENTER);
        style.setVerticalAlignment(CellStyle.VERTICAL_CENTER);
        style.setFont(titleFont);
        styles.put("title", style);

        Font headerFont = wb.createFont();
        headerFont.setFontHeightInPoints((short)11);
        headerFont.setColor(IndexedColors.WHITE.getIndex());
        style = wb.createCellStyle();
        style.setAlignment(CellStyle.ALIGN_CENTER);
        style.setVerticalAlignment(CellStyle.VERTICAL_CENTER);
        style.setFillForegroundColor(IndexedColors.DARK_BLUE.getIndex());
        style.setFillPattern(CellStyle.SOLID_FOREGROUND);
        style.setFont(headerFont);
        style.setWrapText(true);
        styles.put("header", style);
        
        Font overviewFont = wb.createFont();
        overviewFont.setFontHeightInPoints((short)9);
        overviewFont.setColor(IndexedColors.WHITE.getIndex());
        style = wb.createCellStyle();
        style.setAlignment(CellStyle.ALIGN_CENTER);
        style.setVerticalAlignment(CellStyle.VERTICAL_CENTER);
        style.setFillForegroundColor(IndexedColors.DARK_BLUE.getIndex());
        style.setFillPattern(CellStyle.SOLID_FOREGROUND);
        style.setFont(overviewFont);
        style.setWrapText(true);
        styles.put("header", style);

        style = wb.createCellStyle();
        style.setAlignment(CellStyle.ALIGN_CENTER);
        style.setWrapText(true);
        style.setBorderRight(CellStyle.BORDER_THIN);
        style.setRightBorderColor(IndexedColors.BLACK.getIndex());
        style.setBorderLeft(CellStyle.BORDER_THIN);
        style.setLeftBorderColor(IndexedColors.BLACK.getIndex());
        style.setBorderTop(CellStyle.BORDER_THIN);
        style.setTopBorderColor(IndexedColors.BLACK.getIndex());
        style.setBorderBottom(CellStyle.BORDER_THIN);
        style.setBottomBorderColor(IndexedColors.BLACK.getIndex());
        styles.put("cell", style);

        return styles;
    }
}
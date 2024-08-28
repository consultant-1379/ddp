import java.io.IOException;
import java.io.OutputStream;
import java.net.URL;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.jfree.chart.ChartUtilities;
import org.jfree.chart.JFreeChart;

public class PlotServlet extends HttpServlet {
	private static final long serialVersionUID = 1L;

	public void doGet(final HttpServletRequest request, final HttpServletResponse response)
			throws ServletException, IOException {
		final URL url = new URL(request.getRequestURL().toString());
		final String getDataURL = url.getProtocol() + "://" + url.getHost() + request.getParameter("url") + 
				"?action=getdata&" + request.getQueryString(); 
		
		getServletContext().log("URL=" + getDataURL);
		
		final int width = getParam(request,"width",640);
		final int height = getParam(request,"height",480);
		
		final OutputStream out = response.getOutputStream(); /* Get the output stream from the response object */
		try {
			response.setContentType("image/png"); /* Set the HTTP Response Type */

			final PlotFactory pf = new PlotFactory(getDataURL);
			final JFreeChart chart = pf.makeChart();
			ChartUtilities.writeChartAsPNG(out, chart, width, height);/* Write the data to the output stream */
		}
		catch (Exception e) {
		    getServletContext().log("Plot failed",e);
		}
		finally {
			out.close();/* Close the output stream */
		}
	}
	
	private int getParam(final HttpServletRequest request, final String name, final int defaultValue) {
		int result = defaultValue;
		final String strValue = request.getParameter(name);
		if ( strValue != null ) {
			result = Integer.parseInt(strValue);
		}
		return result;
	}
}

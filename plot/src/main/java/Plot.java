import java.awt.*;
import java.net.*;
import javax.swing.*;

import org.jfree.chart.*;

public class Plot extends JApplet
{
	private static final long serialVersionUID = 1L;

	static class MyAuthenticator extends Authenticator {
		PasswordAuthentication pa = null;
		MyAuthenticator(final String userid, final String password) {
			super();
			pa = new PasswordAuthentication(userid,password.toCharArray());
		}

		public PasswordAuthentication getPasswordAuthentication() {
			return pa;
		}
	}

	public static void main(final String[] strings) 
	{
		try {
			new Plot().app(strings);
		} catch (Throwable throwable) {
			throwable.printStackTrace();
		}
	}

	public void init() {
		try             {
			SwingUtilities.invokeAndWait(new Runnable() 
			{
				public void run() 
				{
					Plot.this.createGUI(getParameter("data"));
				}
			});
		} catch (Exception exception) {
			System.err.println("createGUI didn't successfully complete");
			exception.printStackTrace();
		}
	}


	private void createGUI(final String data) {
		try {
			final PlotFactory pf = new PlotFactory(data);
			setContentPane(makeChartPanel(pf.makeChart()));
		} catch (Exception exception) {
			exception.printStackTrace();
		}
	}

	private void app(final String[] args) throws Exception {   
		if ( args.length > 1 ) { 
			Authenticator.setDefault( new MyAuthenticator(args[1],args[2]) );
		}
		
		final PlotFactory pf = new PlotFactory(args[0]);
		try {
			final ChartPanel chartpanel = makeChartPanel(pf.makeChart());
			final JFrame jframe = new JFrame("Chart");
			jframe.setContentPane(chartpanel);
			jframe.pack();
			jframe.setVisible(true);
		} catch ( Exception e ) {
			throw e;
		}
	}

	private ChartPanel makeChartPanel(final JFreeChart jfreechart) throws Exception {
		final ChartPanel chartpanel = new ChartPanel(jfreechart, false, false, true, true, true);
		chartpanel.setPreferredSize(new Dimension(500, 270));
		chartpanel.setMouseZoomable(true, false);
		return chartpanel;
	}


}

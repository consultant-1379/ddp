/*
 * CommandChooser.java
 *
 * Created on 23 juillet 2008, 22:52
 */
package net.atomique.ksar;

import java.awt.GridLayout;
import java.util.Iterator;

/**
 *
 * @author  alex
 */
public class CommandChooser extends javax.swing.JDialog {

    public static final long serialVersionUID = 501L;

    /** Creates new form CommandChooser */
    public CommandChooser(java.awt.Frame parent, boolean modal) {
        super(parent, modal);
        initComponents();
        load_data();
    }

    /** This method is called from within the constructor to
     * initialize the form.
     * WARNING: Do NOT modify this code. The content of this method is
     * always regenerated by the Form Editor.
     */
    // <editor-fold defaultstate="collapsed" desc="Generated Code">//GEN-BEGIN:initComponents
    private void initComponents() {

        commandbtngrp = new javax.swing.ButtonGroup();
        jPanel1 = new javax.swing.JPanel();
        jPanel8 = new javax.swing.JPanel();
        jLabel2 = new javax.swing.JLabel();
        jPanel4 = new javax.swing.JPanel();
        shortcutradio = new javax.swing.JRadioButton();
        shortcutcombo = new javax.swing.JComboBox();
        jPanel3 = new javax.swing.JPanel();
        commandradio = new javax.swing.JRadioButton();
        commandcombo = new javax.swing.JComboBox();
        procshow = new javax.swing.JCheckBox();
        jPanel7 = new javax.swing.JPanel();
        jLabel1 = new javax.swing.JLabel();
        jPanel6 = new javax.swing.JPanel();
        cmdproccombo = new javax.swing.JComboBox();
        jPanel2 = new javax.swing.JPanel();
        cancelButton = new javax.swing.JButton();
        okButton = new javax.swing.JButton();

        setDefaultCloseOperation(javax.swing.WindowConstants.DISPOSE_ON_CLOSE);
        setTitle("Choose command");
        setAlwaysOnTop(true);
        setName("choosecommanddlg"); // NOI18N
        setResizable(false);

        jPanel1.setLayout(new java.awt.BorderLayout());

        jPanel8.setLayout(new java.awt.GridLayout(3, 1));

        jLabel2.setFont(new java.awt.Font("Lucida Grande", 1, 14));
        jLabel2.setHorizontalAlignment(javax.swing.SwingConstants.CENTER);
        jLabel2.setText("Retrieving sar text file");
        jPanel8.add(jLabel2);

        jPanel4.setLayout(new java.awt.FlowLayout(java.awt.FlowLayout.RIGHT));

        commandbtngrp.add(shortcutradio);
        shortcutradio.setSelected(true);
        shortcutradio.setText("Shortcut Command");
        shortcutradio.setHorizontalTextPosition(javax.swing.SwingConstants.LEADING);
        shortcutradio.addActionListener(new java.awt.event.ActionListener() {
            public void actionPerformed(java.awt.event.ActionEvent evt) {
                shortcutradioActionPerformed(evt);
            }
        });
        jPanel4.add(shortcutradio);

        shortcutcombo.setModel(shortcutcommandmodel);
        shortcutcombo.setMinimumSize(new java.awt.Dimension(200, 27));
        shortcutcombo.setPreferredSize(new java.awt.Dimension(200, 27));
        jPanel4.add(shortcutcombo);

        jPanel8.add(jPanel4);

        jPanel3.setLayout(new java.awt.FlowLayout(java.awt.FlowLayout.RIGHT));

        commandbtngrp.add(commandradio);
        commandradio.setText("Command");
        commandradio.setHorizontalTextPosition(javax.swing.SwingConstants.LEADING);
        commandradio.addActionListener(new java.awt.event.ActionListener() {
            public void actionPerformed(java.awt.event.ActionEvent evt) {
                commandradioActionPerformed(evt);
            }
        });
        jPanel3.add(commandradio);

        commandcombo.setEditable(true);
        commandcombo.setModel(commandmodel);
        commandcombo.setEnabled(false);
        commandcombo.setMinimumSize(new java.awt.Dimension(200, 27));
        commandcombo.setPreferredSize(new java.awt.Dimension(200, 27));
        jPanel3.add(commandcombo);

        jPanel8.add(jPanel3);

        jPanel1.add(jPanel8, java.awt.BorderLayout.NORTH);

        procshow.setText("Collect processlist (pidstat only)");
        procshow.setHorizontalAlignment(javax.swing.SwingConstants.CENTER);
        procshow.addActionListener(new java.awt.event.ActionListener() {
            public void actionPerformed(java.awt.event.ActionEvent evt) {
                procshowActionPerformed(evt);
            }
        });
        jPanel1.add(procshow, java.awt.BorderLayout.PAGE_END);

        jPanel7.setBorder(javax.swing.BorderFactory.createLineBorder(new java.awt.Color(0, 0, 0)));
        jPanel7.setEnabled(false);
        jPanel7.setFocusable(false);
        jPanel7.setOpaque(false);
        jPanel7.setVisible(false);
        jPanel7.setLayout(new java.awt.GridLayout(2, 1));

        jLabel1.setFont(new java.awt.Font("Lucida Grande", 1, 14));
        jLabel1.setHorizontalAlignment(javax.swing.SwingConstants.CENTER);
        jLabel1.setText("Process listing method");
        jPanel7.add(jLabel1);

        jPanel6.setFocusable(false);

        cmdproccombo.setModel(new javax.swing.DefaultComboBoxModel(new String[] { "Item 1", "Item 2", "Item 3", "Item 4" }));
        cmdproccombo.setMinimumSize(new java.awt.Dimension(200, 27));
        cmdproccombo.setPreferredSize(new java.awt.Dimension(200, 27));
        jPanel6.add(cmdproccombo);

        jPanel7.add(jPanel6);

        jPanel1.add(jPanel7, java.awt.BorderLayout.CENTER);

        getContentPane().add(jPanel1, java.awt.BorderLayout.CENTER);

        cancelButton.setText("Cancel");
        cancelButton.addActionListener(new java.awt.event.ActionListener() {
            public void actionPerformed(java.awt.event.ActionEvent evt) {
                cancelButtonActionPerformed(evt);
            }
        });
        jPanel2.add(cancelButton);

        okButton.setText("Ok");
        okButton.addActionListener(new java.awt.event.ActionListener() {
            public void actionPerformed(java.awt.event.ActionEvent evt) {
                okButtonActionPerformed(evt);
            }
        });
        jPanel2.add(okButton);

        getContentPane().add(jPanel2, java.awt.BorderLayout.SOUTH);

        pack();
    }// </editor-fold>//GEN-END:initComponents
    private void okButtonActionPerformed(java.awt.event.ActionEvent evt) {//GEN-FIRST:event_okButtonActionPerformed
        this.dispose();
        System.exit(0);
    }//GEN-LAST:event_okButtonActionPerformed

    private void cancelButtonActionPerformed(java.awt.event.ActionEvent evt) {//GEN-FIRST:event_cancelButtonActionPerformed
        this.dispose();
        System.exit(0);
}//GEN-LAST:event_cancelButtonActionPerformed

    private void procshowActionPerformed(java.awt.event.ActionEvent evt) {//GEN-FIRST:event_procshowActionPerformed
        jPanel7.setVisible(procshow.isSelected());
        pack();
}//GEN-LAST:event_procshowActionPerformed

    private void shortcutradioActionPerformed(java.awt.event.ActionEvent evt) {//GEN-FIRST:event_shortcutradioActionPerformed
        tooglecommandbutton(); 
    }//GEN-LAST:event_shortcutradioActionPerformed

    private void commandradioActionPerformed(java.awt.event.ActionEvent evt) {//GEN-FIRST:event_commandradioActionPerformed
        tooglecommandbutton();
    }//GEN-LAST:event_commandradioActionPerformed

    private void load_data() {
        if ( kSarConfig.association_list.isEmpty() ) {
            jPanel8.remove(jPanel4);
            ((GridLayout)jPanel8.getLayout()).setRows(2);
            commandradio.setSelected(true);
            commandcombo.setEnabled(true);
            pack();
        } else {
            for (Iterator<String> it = kSarConfig.association_list.keySet().iterator(); it.hasNext();) {
                String key = it.next();
                shortcutcommandmodel.addElement(key);
            }
        }

        //sshconnectioncmd
        for (Iterator<String> it = kSarConfig.sshconnectioncmd.iterator(); it.hasNext();) {
            String key = it.next();
            commandmodel.addElement(key);
        }
    }

    public String get_command() {
        // test if shortcut command selected
        if ( shortcutradio.isSelected() ) {
            return shortcutcombo.getSelectedItem().toString();
        }
        if ( commandradio.isSelected() ) {
            return commandcombo.getSelectedItem().toString();
        }
        return null;
    }
    
    public String get_proccommand() {
        if ( procshow.isSelected() ) {
            return cmdproccombo.getSelectedItem().toString();
        }
        return null;
    }
    
    private void tooglecommandbutton() {
        shortcutcombo.setEnabled(shortcutradio.isSelected());
        commandcombo.setEnabled(commandradio.isSelected());
    }

    /**
     * @param args the command line arguments
     */
    public static void main(String args[]) {
        java.awt.EventQueue.invokeLater(new Runnable() {

            public void run() {
                CommandChooser dialog = new CommandChooser(new javax.swing.JFrame(), true);
                dialog.addWindowListener(new java.awt.event.WindowAdapter() {

                    public void windowClosing(java.awt.event.WindowEvent e) {
                        System.exit(0);
                    }
                });
                dialog.setVisible(true);
            }
        });
    }
    // Variables declaration - do not modify//GEN-BEGIN:variables
    private javax.swing.JButton cancelButton;
    private javax.swing.JComboBox cmdproccombo;
    private javax.swing.ButtonGroup commandbtngrp;
    private javax.swing.JComboBox commandcombo;
    private javax.swing.JRadioButton commandradio;
    private javax.swing.JLabel jLabel1;
    private javax.swing.JLabel jLabel2;
    private javax.swing.JPanel jPanel1;
    private javax.swing.JPanel jPanel2;
    private javax.swing.JPanel jPanel3;
    private javax.swing.JPanel jPanel4;
    private javax.swing.JPanel jPanel6;
    private javax.swing.JPanel jPanel7;
    private javax.swing.JPanel jPanel8;
    private javax.swing.JButton okButton;
    private javax.swing.JCheckBox procshow;
    private javax.swing.JComboBox shortcutcombo;
    private javax.swing.JRadioButton shortcutradio;
    // End of variables declaration//GEN-END:variables
    private javax.swing.DefaultComboBoxModel shortcutcommandmodel = new javax.swing.DefaultComboBoxModel();
    private javax.swing.DefaultComboBoxModel commandmodel = new javax.swing.DefaultComboBoxModel();

    
}


<?php

function createWindowsCertificateTable() {
    global $statsDB, $hostname;

    $name = 'certificateDetailsHelp';
    $title = 'Security Certificate Expiry Details';
    $table = array('eniq_windows_certificate_details', 'eniq_windows_certi_name_id_mapping',
               'eniq_windows_certi_purpose_id_mapping', 'sites', 'servers');
    $where = $statsDB->where('eniq_windows_certificate_details', 'date', true);
    $where .= " AND servers.id = eniq_windows_certificate_details.serverid
            AND eniq_windows_certi_name_id_mapping.id = eniq_windows_certificate_details.certificateNameId
            AND eniq_windows_certi_purpose_id_mapping.id = eniq_windows_certificate_details.certificatePurposeId
            AND servers.hostname = '$hostname'";

    $table = SqlTableBuilder::init()
            ->name($name)
            ->tables($table)
            ->where($where)
            ->addSimpleColumn("eniq_windows_certi_name_id_mapping.certificateName", "Certificate Name" )
            ->addSimpleColumn("eniq_windows_certi_purpose_id_mapping.certificatePurpose", "Purpose")
            ->addSimpleColumn("eniq_windows_certificate_details.expiryDate", "Expiry Date")
            ->addSimpleColumn("eniq_windows_certificate_details.expiryInDays", "Expiry (in days)")
            ->paginate()
            ->build();

    if ( $table->hasRows() ) {
        echo $table->getTableWithHeader($title, 2, "", "", $name);
        echo addLineBreak(2);
    }
}
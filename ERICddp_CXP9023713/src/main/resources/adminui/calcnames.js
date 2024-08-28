function calcNames(form) {
    var ftpAccEl;
    var ftpAccVisEl;
    var accNameEl;
    var accNameVisEl;
    var owner = "";
    var operator = "";
    var city = "";
    var host = "";
    var network = "";
    for (var i = 0 ; i < form.elements.length ; i++) {
        if (form.elements[i].name == "owner") owner = form.elements[i].value;
        else if (form.elements[i].name == "operator") {
            operator = form.elements[i].options[form.elements[i].selectedIndex].text;
        }
        else if (form.elements[i].name == "city" && form.elements[i].value != "")
            city = "_" + form.elements[i].value.replace(/ /, "_");
        else if (form.elements[i].name == "hostname" && form.elements[i].value != "")
            host = "_" + form.elements[i].value;
        else if (form.elements[i].name == "operator") operator = form.elements[i].value;
        else if (form.elements[i].name == "ftp_acc") ftpAccEl = form.elements[i];
        else if (form.elements[i].name == "ftp_acc_vis") ftpAccVisEl = form.elements[i];
        else if (form.elements[i].name == "acc_name") accNameEl = form.elements[i];
        else if (form.elements[i].name == "acc_name_vis") accNameVisEl = form.elements[i];
        else if (form.elements[i].name == "nettype[Core]" && form.elements[i].checked)
            network = network + "_Core";
        else if (form.elements[i].name == "nettype[GRAN]" && form.elements[i].checked)
            network = network + "_GRAN";
        else if (form.elements[i].name == "nettype[WRAN]" && form.elements[i].checked)
            network = network + "_WRAN";
        else if (form.elements[i].name == "nettype[TDRAN]" && form.elements[i].checked)
            network = network + "_TDRAN";
        else if (form.elements[i].name == "nettype[LTE]" && form.elements[i].checked)
            network = network + "_LTE";
    }
    ftpAccEl.value = operator.toLowerCase() + host.toLowerCase();
    ftpAccEl.value = ftpAccEl.value.split(' ').join('');
    ftpAccVisEl.value = ftpAccEl.value;
    accNameEl.value = operator + city + host.toLowerCase() + network;
    accNameEl.value = accNameEl.value.split(' ').join('');
    accNameVisEl.value = accNameEl.value;
}

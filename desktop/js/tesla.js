
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */



/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

 function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    if (!isset(_cmd.configuration.mode)){
	    _cmd.configuration.mode = 'null';
    }
    if (_cmd.configuration.mode == 'undefined'){
	     _cmd.configuration.mode = 'null';
    }
    console.log("CMD > "+_cmd.name+" > MODE > "+_cmd.configuration.mode);
    if(_cmd.logicalId !== 'create_light_color'){ 
    	var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    }else{
		var tr = '<tr class="cmd hidden" data-cmd_id="' + init(_cmd.id) + '">';  
    }
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
    tr += '</td>';
    tr += '<td>';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label>';
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    }
    tr += '</td>';
    tr += '</tr>';
    if(_cmd.configuration.mode == 'null' || _cmd.configuration.mode == 'undefined' ||  _cmd.configuration.mode == undefined ||  !isset(_cmd.configuration.mode)){
	    $('#table_cmd tbody').append(tr);
		$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
		if (isset(_cmd.type)) {
        	$('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
		}
		jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
    }else{
	    $('#tablecolor_cmd tbody').append(tr);
		$('#tablecolor_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
		if (isset(_cmd.type)) {
        	$('#tablecolor_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
		}
		jeedom.cmd.changeType($('#tablecolor_cmd tbody tr:last'), init(_cmd.subType));
    }
}

$('.eqLogicAttr[data-l1key=configuration][data-l2key=product]').on('change', function () {
	if($(this).value() != '' && $(this).value() != null){
    	$('#img_Model').attr("src", 'plugins/tesla/doc/images/'+$(this).value()+'.png');
	}
});

$('#new_Color').on('click', function () {
  	bootbox.confirm('{{Voulez-vous enregistrer la couleur actuel de votre tesla ? }}', function (result) {
			if (result) {
			var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
        $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/tesla/core/ajax/tesla.ajax.php", // url du fichier php
            data: {
            	action: "savecolor",
            	id_eqLogic : eqLogicId
            },
            dataType: 'json',
            error: function (request, status, error) {
            	handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
            	$('#div_alert').showAlert({message: data.result, level: 'danger'});
            	return;
            }
            $('#div_alert').showAlert({message: '{{Couleur bien enregistrée}}', level: 'success'});
            window.location.href = 'index.php?v=d&p=tesla&m=tesla&id=' + eqLogicId;
            $('#color_tesla_tab').click();
        }
});
}
});
});

$('#bt_scan').on('click', function () {
        bootbox.confirm('{{Voulez-vous lancer une auto découverte de vos tesla }}', function (result) {
			if (result) {
        $.ajax({// fonction permettant de faire de l'ajax
            type: "POST", // methode de transmission des données au fichier php
            url: "plugins/tesla/core/ajax/tesla.ajax.php", // url du fichier php
            data: {
            	action: "scantesla",
            },
            dataType: 'json',
            error: function (request, status, error) {
            	handleAjaxError(request, status, error);
            },
            success: function (data) { // si l'appel a bien fonctionné
            if (data.state != 'ok') {
            	$('#div_alert').showAlert({message: data.result, level: 'danger'});
            	return;
            }
            $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
            location.reload();
			//$('#ul_plugin .li_plugin[data-plugin_id=tesla').click();
        }
});
}
});
});

<?php

function renderSLBDefConfig()
{
	$defaults = getSLBDefaults ();
	startPortlet ('SLB default configs');
	echo '<table cellspacing=0 cellpadding=5 align=center>';
	printOpFormIntro ('save');
	echo '<tr><th class=tdright>VS config</th><td colspan=2><textarea tabindex=103 name=vsconfig rows=10 cols=80>' . htmlspecialchars($defaults['vs']) . '</textarea></td>';
	echo '<td rowspan=2>';
	printImageHREF ('SAVE', 'Save changes', TRUE);
	echo '</td></tr>';
	echo '<tr><th class=tdright>RS config</th><td colspan=2><textarea tabindex=104 name=rsconfig rows=10 cols=80>' . htmlspecialchars($defaults['rs']) . '</textarea></td></tr>';
	echo '</form></table>';
	finishPortlet();
}

function renderSLBEntityCell ($cell, $highlighted = FALSE)
{
	$class = "slbcell realm-${cell['realm']} id-${cell['id']}";
	$a_class = $highlighted ? 'slb-highlighted' : '';

	echo "<table class='$class'>";
	switch ($cell['realm']) {
	case 'object':
		echo "<tr><td><a class='$a_class' href='index.php?page=object&object_id=${cell['id']}'>${cell['dname']}</a>";
		echo "</td></tr><tr><td>";
		printImageHREF ('LB');
		echo "</td></tr>";
		break;
	case 'ipv4vs':
		echo "<tr><td rowspan=3 width='5%'>";
		printImageHREF ('VS');
		echo "</td><td>";
		echo "<a class='$a_class' href='index.php?page=ipv4vs&vs_id=${cell['id']}'>";
		echo $cell['dname'] . "</a></td></tr><tr><td>";
		echo $cell['name'] . '</td></tr>';
		break;
	case 'ipv4rspool':
		echo "<tr><td>";
		echo "<a class='$a_class' href='index.php?page=ipv4rspool&pool_id=${cell['id']}'>";
		echo !strlen ($cell['name']) ? "ANONYMOUS pool [${cell['id']}]" : niftyString ($cell['name']);
		echo "</a></td></tr><tr><td>";
		printImageHREF ('RS pool');
		if ($cell['rscount'])
			echo ' <small>(' . $cell['rscount'] . ')</small>';
		echo "</td></tr>";
		break;
	}
	echo "<tr><td>";
	echo count ($cell['etags']) ? ("<small>" . serializeTags ($cell['etags']) . "</small>") : '&nbsp;';
	echo "</td></tr></table>";

}

function renderSLBEditTab ($entity_id)
{
	global $pageno;
	renderSLBTripletsEdit (spotEntity ($pageno, $entity_id));
}

// called exclusively by renderSLBTripletsEdit. Renders form to add new SLB link.
// realms 1 and 2 are realms to draw inputs for
function renderNewSLBItemForm ($realm1, $realm2)
{
	function print_realm_select_input($realm)
	{
		switch ($realm)
		{
			case 'object':
				echo "<tr valign=top><th class=tdright>Load balancer</th><td class=tdleft>";
				printSelect (getNarrowObjectList ('IPV4LB_LISTSRC'), array ('name' => 'object_id', 'tabindex' => 100));
				break;
			case 'ipv4vs':
				echo '</td></tr><tr><th class=tdright>Virtual service</th><td class=tdleft>';
				printSelect (getIPv4VSOptions(), array ('name' => 'vs_id', 'tabindex' => 101));
				break;
			case 'ipv4rspool':
				echo '</td></tr><tr><th class=tdright>RS pool</th><td class=tdleft>';
				printSelect (getIPv4RSPoolOptions(), array ('name' => 'pool_id', 'tabindex' => 102));
				break;
			default:
				throw new InvalidArgException('realm', $realm);
		}
	}

	startPortlet ('Add new');
	echo "<table cellspacing=0 cellpadding=5 align=center>";
	printOpFormIntro ('addLB');
	print_realm_select_input($realm1);
	echo '</td><td class=tdcenter valign=middle rowspan=2>';
	printImageHREF ('ADD', 'Configure LB', TRUE, 120);
	print_realm_select_input($realm2);
	echo "</td></tr>\n";
	echo "<tr><th class=tdright>VS config</th><td colspan=2><textarea tabindex=110 name=vsconfig rows=10 cols=80></textarea></td></tr>";
	echo "<tr><th class=tdright>RS config</th><td colspan=2><textarea tabindex=111 name=rsconfig rows=10 cols=80></textarea></td></tr>";
	echo "<tr><th class=tdright>Priority</th><td class=tdleft colspan=2><input tabindex=112 name=prio size=10></td></tr>";
	echo "</form></table>\n";
	finishPortlet();
}

// supports object, ipv4vs, ipv4rspool, ipaddress cell types
function renderSLBTriplets ($cell)
{
	$is_cell_ip = (isset ($cell['ip']) and isset ($cell['version']));
	$additional_js_params = $is_cell_ip ? '' : ", {'" . $cell['realm'] . "': " . $cell['id'] . '}';
	$triplets = SLBTriplet::getTriplets ($cell);
	if (count ($triplets))
	{
		$cells = array();
		foreach ($triplets[0]->display_cells as $field)
			$cells[] = $triplets[0]->$field;

		// render table header
		startPortlet ('VS instances (' . count ($triplets) . ')');
		echo "<table cellspacing=0 cellpadding=5 align=center class=widetable><tr>";
		$headers = array
		(
			'object' => 'LB',
			'ipv4vs' => 'VS',
			'ipv4rspool' => 'RS pool',
		);
		foreach ($cells as $slb_cell)
			echo '<th>' . $headers[$slb_cell['realm']] . '</th>';
		foreach (array ('VS config', 'RS config', 'Prio') as $header)
			echo "<th>$header</th>";
		echo "</tr>";

		// render table rows
		global $nextorder;
		$order = 'odd';
		foreach ($triplets as $slb)
		{
			$cells = array();
			foreach ($slb->display_cells as $field)
				$cells[] = $slb->$field;
			echo "<tr valign=top class='row_${order} triplet-row'>";
			foreach ($cells as $slb_cell)
			{
				echo "<td class=tdleft>";
				$highlighted = $is_cell_ip &&
				(
					$slb_cell['realm'] == 'ipv4vs' && $slb->vs['vip'] == $cell['ip'] ||
					$slb_cell['realm'] == 'ipv4rspool' && $slb->vs['vip'] != $cell['ip']
				);
				renderSLBEntityCell ($slb_cell, $highlighted);
				echo "</td>";
			}
			echo "<td class=slbconf>" . htmlspecialchars ($slb->slb['vsconfig']) . "</td>";
			echo "<td class=slbconf>" . htmlspecialchars ($slb->slb['rsconfig']) . "</td>";
			echo "<td class=slbconf>" . htmlspecialchars ($slb->slb['prio']) . "</td>";
			echo "</tr>\n";
			$order = $nextorder[$order];
		}
		echo "</table>\n";
		finishPortlet();
		addJS ('js/slb.js');
	}
}

// renders a list of slb links. it is called from 3 different pages, wich compute their links lists differently.
// each triplet in $triplets array contains balancer, pool, VS cells and config values for triplet: RS, VS configs and pair.
function renderSLBTripletsEdit ($cell)
{
	list ($realm1, $realm2) = array_values (array_diff (array ('object', 'ipv4vs', 'ipv4rspool'), array ($cell['realm'])));
	if (getConfigVar ('ADDNEW_AT_TOP') == 'yes')
		renderNewSLBItemForm($realm1, $realm2);

	$triplets = SLBTriplet::getTriplets ($cell);
	if (count ($triplets))
	{
		$cells = array();
		foreach ($triplets[0]->display_cells as $field)
			$cells[] = $triplets[0]->$field;

		startPortlet ('Manage existing (' . count ($triplets) . ')');
		echo "<table cellspacing=0 cellpadding=5 align=center class=cooltable>\n";
		global $nextorder;
		$order = 'odd';
		foreach ($triplets as $slb)
		{
			$cells = array();
			foreach ($slb->display_cells as $field)
				$cells[] = $slb->$field;
			$ids = array
			(
				'object_id' => $slb->lb['id'],
				'vs_id' => $slb->vs['id'],
				'pool_id' => $slb->rs['id'],
			);
			$del_params = $ids;
			$del_params['op'] = 'delLB';
			printOpFormIntro ('updLB', $ids);
			echo "<tr valign=top class=row_${order}><td rowspan=2 class=tdright valign=middle><a href='".makeHrefProcess($del_params)."'>";
			printImageHREF ('DELETE', 'Unconfigure');
			echo "</a></td><td class=tdleft valign=bottom>";
			renderSLBEntityCell ($cells[0]);
			echo "</td><td>VS config &darr;<br><textarea name=vsconfig rows=5 cols=70>" . htmlspecialchars ($slb->slb['vsconfig']) . "</textarea></td>";
			echo '<td class=tdleft rowspan=2 valign=middle>';
			printImageHREF ('SAVE', 'Save changes', TRUE);
			echo "</td>";
			echo "</tr><tr class=row_${order}><td class=tdleft valign=top>";
			renderSLBEntityCell ($cells[1]);
			echo '</td><td>';
			echo "<textarea name=rsconfig rows=5 cols=70>" . htmlspecialchars ($slb->slb['rsconfig']) . "</textarea><br>RS config &uarr;";
			echo "<div style='float:left; margin-top:10px'><label><input name=prio type=text size=10 value=\"" . htmlspecialchars ($slb->slb['prio']) . "\"> &larr; Priority</label></div>";
			echo '</td></tr></form>';
			$order = $nextorder[$order];
		}
		echo "</table>\n";
		finishPortlet();
	}

	if (getConfigVar ('ADDNEW_AT_TOP') != 'yes')
		renderNewSLBItemForm ($realm1, $realm2);
}

function renderLBList ()
{
	$cells = array();
	foreach (getLBList() as $object_id => $poolcount)
		$cells[$object_id] = spotEntity ('object', $object_id);
	renderCellList ('object', 'items', FALSE, $cells);
}

function renderRSPool ($pool_id)
{
	$poolInfo = spotEntity ('ipv4rspool', $pool_id);

	echo "<table border=0 class=objectview cellspacing=0 cellpadding=0>";
	if (strlen ($poolInfo['name']))
		echo "<tr><td colspan=2 align=center><h1>{$poolInfo['name']}</h1></td></tr>";
	echo "<tr><td class=pcleft>\n";

	$summary = array();
	$summary['Pool name'] = $poolInfo['name'];
	$summary['Real servers'] = $poolInfo['rscount'];
	$summary['VS instances'] = $poolInfo['refcnt'];
	$summary['tags'] = '';
	$summary['VS configuration'] = '<div class="dashed slbconf">' . htmlspecialchars ($poolInfo['vsconfig']) . '</div>';
	$summary['RS configuration'] = '<div class="dashed slbconf">' . htmlspecialchars ($poolInfo['rsconfig']) . '</div>';
	renderEntitySummary ($poolInfo, 'Summary', $summary);

	if ($poolInfo['rscount'])
	{
		startPortlet ("Real servers ({$poolInfo['rscount']})");
		echo "<table cellspacing=0 cellpadding=5 align=center class=widetable>\n";
		echo "<tr><th>in service</th><th>address</th><th>port</th><th>RS configuration</th></tr>";
		foreach (getRSListInPool ($pool_id) as $rs)
		{
			echo "<tr valign=top><td align=center>";
			if ($rs['inservice'] == 'yes')
				printImageHREF ('inservice', 'in service');
			else
				printImageHREF ('notinservice', 'NOT in service');
			echo "</td><td class=tdleft><a href='".makeHref(array('page'=>'ipaddress', 'ip'=>$rs['rsip']))."'>${rs['rsip']}</a></td>";
			echo "<td class=tdleft>${rs['rsport']}</td><td class=slbconf>${rs['rsconfig']}</td></tr>\n";
		}
		echo "</table>\n";
		finishPortlet();
	}

	echo "</td><td class=pcright>\n";
	renderSLBTriplets ($poolInfo);
	echo "</td></tr><tr><td colspan=2>\n";
	renderFilesPortlet ('ipv4rspool', $pool_id);
	echo "</td></tr></table>\n";
}

function renderRSPoolServerForm ($pool_id)
{
	global $nextorder;
	$poolInfo = spotEntity ('ipv4rspool', $pool_id);

	if ($poolInfo['rscount'])
	{
		startPortlet ("Manage existing (${poolInfo['rscount']})");
		echo "<table cellspacing=0 cellpadding=5 align=center class=cooltable>\n";
		echo "<tr><th>&nbsp;</th><th>Address</th><th>Port</th><th>in service</th><th>configuration</th><th>&nbsp;</th></tr>\n";
		$order = 'odd';
		foreach (getRSListInPool ($pool_id) as $rsid => $rs)
		{
			printOpFormIntro ('updRS', array ('rs_id' => $rsid));
			echo "<tr valign=top class=row_${order}><td><a href='".makeHrefProcess(array('op'=>'delRS', 'pool_id'=>$pool_id, 'id'=>$rsid))."'>";
			printImageHREF ('delete', 'Delete this real server');
			echo "</td><td><input type=text name=rsip value='${rs['rsip']}'></td>";
			echo "<td><input type=text name=rsport size=5 value='${rs['rsport']}'></td>";
			$checked = $rs['inservice'] == 'yes' ? 'checked' : '';
			echo "<td><input type=checkbox name=inservice $checked></td>";
			echo "<td><textarea name=rsconfig>${rs['rsconfig']}</textarea></td><td>";
			printImageHREF ('SAVE', 'Save changes', TRUE);
			echo "</td></tr></form>\n";
			$order = $nextorder[$order];
		}
		echo "</table>\n";
		finishPortlet();
	}

	startPortlet ('Add one');
	echo "<table cellspacing=0 cellpadding=5 align=center class=widetable>\n";
	echo "<tr><th>in service</th><th>Address</th><th>Port</th><th>&nbsp;</th></tr>\n";
	printOpFormIntro ('addRS');
	echo "<tr><td>";
	if (getConfigVar ('DEFAULT_IPV4_RS_INSERVICE') == 'yes')
		printImageHREF ('inservice', 'in service');
	else
		printImageHREF ('notinservice', 'NOT in service');
	echo "</td><td><input type=text name=remoteip id=remoteip tabindex=1> ";
	echo "<a href='javascript:;' onclick='window.open(\"" . makeHrefForHelper ('inet4list');
	echo "\", \"findobjectip\", \"height=700, width=400, location=no, menubar=no, resizable=yes, scrollbars=no, status=no, titlebar=no, toolbar=no\");'>";
	printImageHREF ('find', 'pick address');
	echo "</a></td>";
	$default_port = getConfigVar ('DEFAULT_SLB_RS_PORT');
	if ($default_port == 0)
		$default_port = '';
	echo "<td><input type=text name=rsport size=5 value='${default_port}'  tabindex=2></td><td>";
	printImageHREF ('add', 'Add new', TRUE, 3);
	echo "</td></tr><tr><th colspan=4>configuration</th></tr>";
	echo "<tr><td colspan=4><textarea name=rsconfig rows=10 cols=80 tabindex=4></textarea></td></tr>";
	echo "</form></table>\n";
	finishPortlet();

	startPortlet ('Add many');
	printOpFormIntro ('addMany');
	echo "<table border=0 align=center>\n<tr><td>";
	if (getConfigVar ('DEFAULT_IPV4_RS_INSERVICE') == 'yes')
		printImageHREF ('inservice', 'in service');
	else
		printImageHREF ('notinservice', 'NOT in service');
	echo "</td><td>Format: ";
	$formats = array
	(
		'ssv_1' => 'SSV: &lt;IP address&gt;',
		'ssv_2' => 'SSV: &lt;IP address&gt; &lt;port&gt;',
		'ipvs_2' => 'ipvsadm -l -n (address and port)',
		'ipvs_3' => 'ipvsadm -l -n (address, port and weight)',
	);
	printSelect ($formats, array ('name' => 'format'), 'ssv_1');
	echo "</td><td><input type=submit value=Parse></td></tr>\n";
	echo "<tr><td colspan=3><textarea name=rawtext cols=100 rows=50></textarea></td></tr>\n";
	echo "</table>\n";
	finishPortlet();
}

function renderRSPoolList ()
{
	renderCellList ('ipv4rspool', 'RS pools');
}

function renderRealServerList ()
{
	global $nextorder;
	$rslist = getRSList ();
	$pool_list = listCells ('ipv4rspool');
	echo "<table class=widetable border=0 cellpadding=10 cellspacing=0 align=center>\n";
	echo "<tr><th>RS pool</th><th>in service</th><th>real IP address</th><th>real port</th><th>RS configuration</th></tr>";
	$order = 'even';
	$last_pool_id = 0;
	foreach ($rslist as $rsinfo)
	{
		if ($last_pool_id != $rsinfo['rspool_id'])
		{
			$order = $nextorder[$order];
			$last_pool_id = $rsinfo['rspool_id'];
		}
		echo "<tr valign=top class=row_${order}><td><a href='".makeHref(array('page'=>'ipv4rspool', 'pool_id'=>$rsinfo['rspool_id']))."'>";
		echo !strlen ($pool_list[$rsinfo['rspool_id']]['name']) ? 'ANONYMOUS' : $pool_list[$rsinfo['rspool_id']]['name'];
		echo '</a></td><td align=center>';
		if ($rsinfo['inservice'] == 'yes')
			printImageHREF ('inservice', 'in service');
		else
			printImageHREF ('notinservice', 'NOT in service');
		echo "</td><td><a href='".makeHref(array('page'=>'ipaddress', 'ip'=>$rsinfo['rsip']))."'>${rsinfo['rsip']}</a></td>";
		echo "<td>${rsinfo['rsport']}</td>";
		echo "<td><pre>${rsinfo['rsconfig']}</pre></td>";
		echo "</tr>\n";
	}
	echo "</table>";
}


function editRSPools ()
{
	function printNewItemTR()
	{
		startPortlet ('Add new');
		printOpFormIntro ('add');
		echo "<table border=0 cellpadding=10 cellspacing=0 align=center>";
		echo "<tr><th class=tdright>Name</th>";
		echo "<td class=tdleft><input type=text name=name tabindex=101></td><td>";
		printImageHREF ('CREATE', 'create real server pool', TRUE, 104);
		echo "</td><th>Assign tags</th></tr>";
		echo "<tr><th class=tdright>VS config</th><td colspan=2><textarea name=vsconfig rows=10 cols=80 tabindex=102></textarea></td>";
		echo "<td rowspan=2>";
		renderNewEntityTags ('ipv4rspool');
		echo "</td></tr>";
		echo "<tr><th class=tdright>RS config</th><td colspan=2><textarea name=rsconfig rows=10 cols=80 tabindex=103></textarea></td></tr>";
		echo "</table></form>";
		finishPortlet();
	}

	if (getConfigVar ('ADDNEW_AT_TOP') == 'yes')
		printNewItemTR();
	if (count ($pool_list = listCells ('ipv4rspool')))
	{
		startPortlet ('Delete existing (' . count ($pool_list) . ')');
		echo "<table class=cooltable border=0 cellpadding=10 cellspacing=0 align=center>\n";
		global $nextorder;
		$order='odd';
		foreach ($pool_list as $pool_info)
		{
			echo "<tr valign=top class=row_${order}><td valign=middle>";
			if ($pool_info['refcnt'])
				printImageHREF ('NODESTROY', 'RS pool is used ' . $pool_info['refcnt'] . ' time(s)');
			else
			{
				echo "<a href='".makeHrefProcess(array('op'=>'del', 'pool_id'=>$pool_info['id']))."'>";
				printImageHREF ('DESTROY', 'delete real server pool');
				echo '</a>';
			}
			echo '</td><td class=tdleft>';
			renderCell ($pool_info);
			echo '</td></tr>';
			$order = $nextorder[$order];
		}
		echo "</table>";
		finishPortlet();
	}
	if (getConfigVar ('ADDNEW_AT_TOP') != 'yes')
		printNewItemTR();
}

function renderVirtualService ($vsid)
{
	$vsinfo = spotEntity ('ipv4vs', $vsid);
	echo '<table border=0 class=objectview cellspacing=0 cellpadding=0>';
	if (strlen ($vsinfo['name']))
		echo "<tr><td colspan=2 align=center><h1>${vsinfo['name']}</h1></td></tr>\n";
	echo '<tr>';

	echo '<td class=pcleft>';
	$summary = array();
	$summary['Name'] = $vsinfo['name'];
	$summary['Protocol'] = $vsinfo['proto'];
	$summary['Virtual IP address'] = "<a href='".makeHref(array('page'=>'ipaddress', 'tab'=>'default', 'ip'=>$vsinfo['vip']))."'>${vsinfo['vip']}</a>";
	$summary['Virtual port'] = $vsinfo['vport'];
	$summary['tags'] = '';
	$summary['VS configuration'] = '<div class="dashed slbconf">' . $vsinfo['vsconfig'] . '</div>';
	$summary['RS configuration'] = '<div class="dashed slbconf">' . $vsinfo['rsconfig'] . '</div>';
	renderEntitySummary ($vsinfo, 'Summary', $summary);
	echo '</td>';

	echo '<td class=pcright>';
	renderSLBTriplets ($vsinfo);
	echo '</td></tr><tr><td colspan=2>';
	renderFilesPortlet ('ipv4vs', $vsid);
	echo '</tr><table>';
}

function renderVSList ()
{
	renderCellList ('ipv4vs', 'Virtual services');
}

function renderVSListEditForm ()
{
	global $nextorder;
	$protocols = array ('TCP' => 'TCP', 'UDP' => 'UDP');

	function printNewItemTR ($protocols)
	{
		startPortlet ('Add new');
		printOpFormIntro ('add');
		echo "<table border=0 cellpadding=10 cellspacing=0 align=center>\n";
		echo "<tr valign=bottom><td>&nbsp;</td><th>VIP</th><th>port</th><th>proto</th><th>name</th><th>&nbsp;</th><th>Assign tags</th></tr>";
		echo '<tr valign=top><td>&nbsp;</td>';
		echo "<td><input type=text name=vip tabindex=101></td>";
		$default_port = getConfigVar ('DEFAULT_SLB_VS_PORT');
		if ($default_port == 0)
			$default_port = '';
		echo "<td><input type=text name=vport size=5 value='${default_port}' tabindex=102></td><td>";
		printSelect ($protocols, array ('name' => 'proto'), 'TCP');
		echo '</td><td><input type=text name=name tabindex=104></td><td>';
		printImageHREF ('CREATE', 'create virtual service', TRUE, 105);
		echo "</td><td rowspan=3>";
		renderNewEntityTags ('ipv4vs');
		echo "</td></tr><tr><th>VS configuration</th><td colspan=5 class=tdleft><textarea name=vsconfig rows=10 cols=80></textarea></td>";
		echo "<tr><th>RS configuration</th><td colspan=5 class=tdleft><textarea name=rsconfig rows=10 cols=80></textarea></td></tr>";
		echo '</table></form>';
		finishPortlet();
	}

	if (getConfigVar ('ADDNEW_AT_TOP') == 'yes')
		printNewItemTR ($protocols);

	if (count ($vslist = listCells ('ipv4vs')))
	{
		startPortlet ('Delete existing (' . count ($vslist) . ')');
		echo '<table class=cooltable border=0 cellpadding=10 cellspacing=0 align=center>';
		$order = 'odd';
		foreach ($vslist as $vsid => $vsinfo)
		{
			echo "<tr valign=top class=row_${order}><td valign=middle>";
			if ($vsinfo['refcnt'])
				printImageHREF ('NODESTROY', 'there are ' . $vsinfo['refcnt'] . ' RS pools configured');
			else
			{
				echo "<a href='".makeHrefProcess(array('op'=>'del', 'vs_id'=>$vsid))."'>";
				printImageHREF ('DESTROY', 'delete virtual service');
				echo '</a>';
			}
			echo "</td><td class=tdleft>";
			renderCell ($vsinfo);
			echo "</td></tr>";
			$order = $nextorder[$order];
		}
		echo "</table>";
		finishPortlet();
	}
	if (getConfigVar ('ADDNEW_AT_TOP') != 'yes')
		printNewItemTR ($protocols);
}

function renderEditRSPool ($pool_id)
{
	$poolinfo = spotEntity ('ipv4rspool', $pool_id);
	printOpFormIntro ('updIPv4RSP');
	echo '<table border=0 align=center>';
	echo "<tr><th class=tdright>name:</th><td class=tdleft><input type=text name=name value='${poolinfo['name']}'></td></tr>\n";
	echo "<tr><th class=tdright>VS config:</th><td class=tdleft><textarea name=vsconfig rows=20 cols=80>${poolinfo['vsconfig']}</textarea></td></tr>\n";
	echo "<tr><th class=tdright>RS config:</th><td class=tdleft><textarea name=rsconfig rows=20 cols=80>${poolinfo['rsconfig']}</textarea></td></tr>\n";
	echo "<tr><th class=submit colspan=2>";
	printImageHREF ('SAVE', 'Save changes', TRUE);
	echo "</td></tr>\n";
	echo "</table></form>\n";
}

function renderEditVService ($vsid)
{
	$vsinfo = spotEntity ('ipv4vs', $vsid);
	printOpFormIntro ('updIPv4VS');
	echo '<table border=0 align=center>';
	echo "<tr><th class=tdright>VIP:</th><td class=tdleft><input tabindex=1 type=text name=vip value='${vsinfo['vip']}'></td></tr>\n";
	echo "<tr><th class=tdright>port:</th><td class=tdleft><input tabindex=2 type=text name=vport value='${vsinfo['vport']}'></td></tr>\n";
	echo "<tr><th class=tdright>proto:</th><td class=tdleft>";
	printSelect (array ('TCP' => 'TCP', 'UDP' => 'UDP'), array ('name' => 'proto'), $vsinfo['proto']);
	echo "</td></tr>\n";
	echo "<tr><th class=tdright>name:</th><td class=tdleft><input tabindex=4 type=text name=name value='${vsinfo['name']}'></td></tr>\n";
	echo "<tr><th class=tdright>VS config:</th><td class=tdleft><textarea tabindex=5 name=vsconfig rows=20 cols=80>${vsinfo['vsconfig']}</textarea></td></tr>\n";
	echo "<tr><th class=tdright>RS config:</th><td class=tdleft><textarea tabindex=6 name=rsconfig rows=20 cols=80>${vsinfo['rsconfig']}</textarea></td></tr>\n";
	echo "<tr><th class=submit colspan=2>";
	printImageHREF ('SAVE', 'Save changes', TRUE, 7);
	echo "</td></tr>\n";
	echo "</table></form>\n";
}

function renderLVSConfig ($object_id)
{
	echo '<br>';
	try
	{
		$config = buildLVSConfig ($object_id);

		printOpFormIntro ('submitSLBConfig');
		echo "<center><input type=submit value='Submit for activation'></center>";
		echo "</form>";
	}
	catch(RTBuildLVSConfigError $e)
	{
		$config = $e->config_to_display;
		foreach ($e->message_list as $msg)
			echo '<div class="msg_error">' . $msg . '</div>';
	}
	echo "<pre>$config</pre>";
}

?>
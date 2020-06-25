<?php
	session_start();
	// config
	define('VERSION','v0.5');
	define('PASSWD',''); // add your sha1 password here if you want a small protection https://www.sha1.fr/
	$cmds = array(
		"SELECT" => "SELECT * FROM `table_name` WHERE condition1, condition2 ORDER BY column1;",
		"UPDATE" => "UPDATE `table_name` SET column1 = value1, column2 = value2 WHERE condition1, condition2;",
		"INSERT" => "INSERT INTO `table_name` (column1, column2) VALUES (value1, value2);",
		"CREATE" => "CREATE TABLE `table_name` (column1 INTEGER, column2 TEXT);",
		"ALTER" => "ALTER TABLE `table_name` ADD column3 TEXT;",
		"DROP" => "DROP TABLE `table_name`;",
		"DELETE" => "DELETE FROM `table_name` WHERE condition1, condition2;"
	);
	// render view
	function render($html){
		echo '<!DOCTYPE html>
				<html>
					<head>
						<meta charset="utf-8">
						<meta name="viewport" content="width=device-width, initial-scale=1">
						<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mini.css/3.0.1/mini-default.min.css">
						<title>Sqlitor</title>
						<style>
							table *:not(th){
								font-size:0.7rem;
							}
							table:not(.horizontal) td{
								white-space: nowrap;
								overflow: hidden;
								text-overflow: ellipsis;
								width: 200px;
							}
							.historyelement{
								white-space: nowrap;
								overflow: hidden;
								text-overflow: ellipsis;
								cursor:grab;
								color: dimgray;
								font-size:0.9rem;
							}
							.historyelement:hover {
								color: steelblue;
							}
						</style>
					</head>
					<body>
						<div class="container">'.
						$html
						.'</div>
					</body>
				</html>';
		die();
	}
	// render an array as JSON
	function renderResultToJson($array){
		header('Content-Type: application/json');
		echo json_encode($array);
		die();
	}
	// render a SQL command file
	function renderToSql($sql){
		header('Content-Type: application/sql');
		header("Content-Disposition: attachment; filename=export.sql");
		echo $sql;
		die();
	}
	// render an array as CSV
	function renderResultToCSV($array){
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=export.csv");
		ob_start();
		$df = fopen("php://output", 'w');
		fputcsv($df, array_keys($array[0]));
		foreach ($array as $row) {
		fputcsv($df, $row);
		}
		fclose($df);
		echo ob_get_clean();
		die();
	}
	// construct html array of results
	function constructArray($datas,$primaryKey = null, $tableName = null){
		$table="<div style='overflow-x:auto;max-width:100%;'>";
		$table.="<table class='striped hoverable' style='max-height:60vh;width:".(count($datas[0])*200)."px;'>";
		$table.="<thead>";
		$table.="<tr>";
		foreach(array_keys($datas[0]) as $column){
			if(empty($tableName) || empty($primaryKey)){
				$table.="<th".(($column=='rowid')?" style='color: goldenrod;'":"").">".$column."</th>";
			}else{
				$table.="<th".(($column==$primaryKey || $column=='rowid')?" style='color: goldenrod;'":"").">".$column." <a href=\"?explore=$tableName&asc=$column\"><span class=\"icon-upload\" style='transform: rotate(180deg);'></span></a> <a href=\"?explore=$tableName&desc=$column\"><span class=\"icon-upload\"></span></a></th>";
			}
		}
		if(!empty($table)){
			$table.="<th></th>";
		}
		$table.="</tr>";
		$table.="</thead>";
		$table.="<tbody>";
		foreach($datas as $row){
			$table.="<tr>";
			foreach($row as $column => $value){
				if(empty($tableName) || empty($primaryKey) ){
					$table.="<td data-label=\"$column\" ".(($column=='rowid')?"style='font-weight:bold;'":"").">".$value."</td>";
				}else{
					$table.="<td data-label=\"$column\" ".(($column==$primaryKey || $column=='rowid')?"style='font-weight:bold;'":"")." onclick=\"editThisData(this,'$tableName','$primaryKey','$column')\" data-edit=\"false\"  data-primaryval=\"".$row[$primaryKey]."\" data-original=\"".base64_encode($value)."\">".$value."</td>";
				}
			}
			if(!empty($tableName)){
				$rowWithoutRowId = $row;
				unset($rowWithoutRowId['rowid']);
				$columns = implode(',',array_keys($row));
				$values = implode("\",\"",$row);
				$sqlInsert = "INSERT INTO $tableName ($columns) VALUES (\"$values\");";
				$table.="<td>";
				$table.="<a href=\"javascript:proposeSql('".base64_encode($sqlInsert)."')\">clone</a>";
				if(!empty($primaryKey)){
					$sqlDelete = "DELETE FROM $tableName WHERE  $primaryKey=\"".$row[$primaryKey]."\";";
					$table.=" &bull; <a href=\"javascript:proposeSql('".base64_encode($sqlDelete)."')\">delete</a>";
				}
				echo "</td>";
			}
			$table.="</tr>";
		}
		$table.="</tbody>";
		$table.="</table>";
		$table.="</div>";
		return $table;
	}
	// if user quit
	if($_POST['action']=='quit'){
		session_destroy();
		render("<pre>Session ended. <br><a href=\"sqlitor.php\">Return to database connection</a></pre>");
	}
	// if user execute sql we populate the session history
	if(!empty($_POST['sql'])){
		$_SESSION['history'][] = $_POST['sql'];
		$_SESSION['history'] = array_unique($_SESSION['history']);
	}

	// if user connect to a db or is allready connected
	if(!empty($_POST['db'])) $_SESSION['db'] = $_POST['db'];
	if(!empty($_POST['password'])) $_SESSION['PASSWD'] = sha1($_POST['password']);
	extract($_SESSION);

	// render login if no db connexion
	if(empty($db) || ( $PASSWD!=PASSWD && PASSWD != "" ) ){ 
		render('
			<div class="row">
				<div class="col-lg-6 col-lg-offset-3 col-md-6 col-md-offset-3 col-sm-12">
					<h1>SQLITOR '.VERSION.'</h1>
					<form method="post">
						<select name="open"><option>OPEN</option><option>CREATE</option></select>
						<input name="db" type="text" placeholder="Database" required>'.((!empty(PASSWD))?"<input type=\"password\" name=\"password\" placeholder=\"Password\" required>":"").'
						<input type="submit" value="CONNECT">
					</form>
				</div>
			</div>
		');
	}else{

		if(!file_exists($db) && $_POST['open']!='CREATE'){
			session_destroy();
			render("<pre>the file does not exist : ".$db." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>");
		}
		if(file_exists($db) && $_POST['open']=='CREATE'){
			session_destroy();
			render("<pre>the file already exists : ".$db." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>");
		}
		try{
			$pdo = new PDO('sqlite:'.$db);
			$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(Exception $e) {
			session_destroy();
			render("<pre>Unable to open the database : ".$e->getMessage()." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>");
		}

		if( !empty($_GET['exportcsv']) || !empty($_GET['exportjson']) ){
			// user want to export 
			if(!empty($_GET['exportjson'])) $sql = base64_decode($_GET['exportjson']);
			if(!empty($_GET['exportcsv'])) $sql = base64_decode($_GET['exportcsv']);
			try{
				$results = $pdo->query($sql);
				if($results){
					$array = $results->fetchAll();
					if(count($array)>0){
						if(!empty($_GET['exportjson'])) renderResultToJson($array);
						if(!empty($_GET['exportcsv'])) renderResultToCSV($array);
					}else{
						render("<pre>the file already exists : ".$db." <br><a href=\"sqlitor.php\">Go back to Sqlitor</a></pre>");
					}
				}
			} catch(Exception $e) {
				render("<pre>Unable to export your data : ".$db." <br><a href=\"sqlitor.php\">Go back to Sqlitor</a></pre>");
			} 
			render("<pre>Unable to export your data : ".$db." <br><a href=\"sqlitor.php\">Go back to Sqlitor</a></pre>");

		}elseif(!empty($_POST['sql'])){
			// result layout if user execute a simple sql
			$resultlayout .= '<div class="row">';
			$resultlayout .= '<div class="col-lg-12 col-md-12">';
			$sqls = explode(";",$_POST['sql']);
			$i=0;
			foreach($sqls as $sql){
				$sql = trim($sql);
				if(!empty($sql)){
					$resultlayout .= '<div id="result'.$i.'">';
					try{
						$results = $pdo->query($sql.";");
						if($results){
							$array = $results->fetchAll();
							$affected=$results->rowCount();
							$affected=$results->rowCount();
							$resultlayout .= '<pre>'.$sql.';<br><mark class="tertiary">Request successfully executed</mark>'.(($affected>0)?"<br>Affected rows: $affected":"").((count($array)>0)?"<br>Selected rows: ".count($array):"").'</pre>';
							if(count($array)>0) $resultlayout .= constructArray($array);
						}
					} catch(Exception $e) {
						$resultlayout .= '<pre>'.$sql.';<br><mark class="secondary" style="word-wrap:anywhere">'.$e->getMessage().'</mark></pre>';
					} 
					$resultlayout .= '<p style="text-align:right;">';
					if(count($array)>0) $resultlayout .= '<a href="?exportcsv='.base64_encode($sql).'" target="_blank" class="button small" style="float:right;">EXPORT TO CSV</a><a href="?exportjson='.base64_encode($sql).'" target="_blank" class="button small" style="float:right;">EXPORT TO JSON</a>';
					$resultlayout .= '<button class="small" onclick="document.getElementById(\'result'.$i.'\').remove();">DISMISS</button>';
					$resultlayout .= '</p>';
					$resultlayout .= '</div>';
					$i++;
				}
			}
			$resultlayout .= '</div>';
			$resultlayout .= '</div>';
		}elseif(!empty($_GET['explore'])){
			// result layout if user explore a table
			$t = $_GET['explore'];
			if(!empty($_GET['asc'])) $order = " ORDER BY ".$_GET['asc']." ASC";
			if(!empty($_GET['desc'])) $order = " ORDER BY ".$_GET['desc']." DESC";
			$resultlayout .= '<div class="row">';
			$resultlayout .= '<div class="col-lg-12 col-md-12">';
			$columns = $pdo->query("PRAGMA table_info($t)")->fetchAll();
			$primary = null;
			foreach($columns as $column){ if($column['pk']==1) $primary = $column['name']; }
			if($primary == null) $primary = 'rowid';
			$sql = "SELECT $primary,* FROM $t".$order;
			$rowCount = 0;
			try{
				$results = $pdo->query($sql.";");
				if($results){
					$array = $results->fetchAll();
					$rowCount = count($array);
					$resultlayout .= '<pre>'.$sql.";<br>Selected rows: $rowCount</pre>";
					if(empty($_GET['page'])) $_GET['page']=1; else $_GET['page'] = intval($_GET['page']);
					$array = array_slice($array,($_GET['page']-1)*50,50);
					if(count($array)>0){
						if($_GET['export']=='json') renderResultToJson($array);
						if($_GET['export']=='csv') renderResultToCSV($array);
						$resultlayout .= constructArray($array,$primary,$t);
					}
				}
			} catch(Exception $e) {
				$resultlayout .= '<pre>'.$sql.';<br><mark class="secondary" style="word-wrap:anywhere">'.$e->getMessage().'</mark></pre>';
			}
			$resultlayout .= '<p>';
			$pageCount = ceil($rowCount/50);
			$link = "?explore=$t";
			if(!empty($_GET['asc'])) $link .= "&asc=".$_GET['asc'];
			if(!empty($_GET['desc'])) $link .= "&desc=".$_GET['desc'];
			if($pageCount<20){
				for($i=1;$i<=$pageCount;$i++){
					$resultlayout .= '<a href="'.$link.'&page='.$i.'" class="button small '.(($i==$_GET['page'])?"primary":"").'">'.$i.'</a>';
				}
			}else{
				if($_GET['page']>6) $resultlayout .= '<a href="'.$link.'&page=1" class="button small"><<</a>';
				for($i=$_GET['page']-5;$i<$_GET['page'];$i++){
					if($i>0) $resultlayout .= '<a href="'.$link.'&page='.$i.'" class="button small '.(($i==$_GET['page'])?"primary":"").'">'.$i.'</a>';
				}
				$resultlayout .= '<a href="'.$link.'&page='.$_GET['page'].'" class="button small primary">'.$_GET['page'].'</a>';
				for($i=$_GET['page']+1;$i<$_GET['page']+6;$i++){
					if($i<=$pageCount) $resultlayout .= '<a href="'.$link.'&page='.$i.'" class="button small '.(($i==$_GET['page'])?"primary":"").'">'.$i.'</a>';
				}
				if($_GET['page']<$pageCount-6) $resultlayout .= '<a href="'.$link.'&page='.$pageCount.'" class="button small">>></a>';
			}
			$resultlayout .= '<button onclick="SaveAllEditData()" class="small" style="float:right;">SAVE ALL</button><button onclick="CancelAllEditData()" class="small" style="float:right;">CANCEL ALL</button>';
			$resultlayout .= '<a href="'.$link.'&export=csv" target="_blank" class="button small" style="float:right;">EXPORT TO CSV</a><a href="'.$link.'&export=json" target="_blank" class="button small" style="float:right;">EXPORT TO JSON</a>';
			$resultlayout .= '</p>';
			$resultlayout .= '</div>';
			$resultlayout .= '</div>';
		}elseif(!empty($_GET['export'])){
			// result layout if user explore a table
			$t = $_GET['export'];
			$sql = "";
			// drop table
			// $sql .= "-- DROP DATATABLE ;\n";
			// $sql .= "DROP TABLE IF EXISTS `$t`;\n\n";
			// create table from schema
			$sql .= "-- CREATE DATATABLE ;\n";
			$sql .= array_column($pdo->query("SELECT sql FROM sqlite_master WHERE type ='table' AND name = '$t';")->fetchAll(),'sql')[0].";\n\n";
			
			$schema = $pdo->query("PRAGMA table_info($t)")->fetchAll();
			$data = $pdo->query("SELECT * FROM `$t`;")->fetchAll();

			if(count($data)>0){
				$sql .= "-- INSERT DATA ;\n";
				$sql .= "INSERT INTO `$t` VALUES \n";
				$insertedValues = array();
				foreach($data as $result){
					$insertedValues[]= "(\"".implode("\",\"",$result)."\")";
				}
				$sql .= implode(",\n",$insertedValues).";";
			}
			
			renderToSql($sql);
		}
		// top bar layout
		$toplayout = '
			<form method="post">
				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-12">
						<h1>> '.$db.' </h1>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-12" style="text-align:right;">
						<a href="'.$db.'" class="button" download>Download database</a>
						<input type="hidden" name="action" value="quit"><input class="secondary" type="submit" value="Quit this database">
					</div>
				</div>
			</form>
		';
		// list of basic SQL cmds btns
		foreach($cmds as $c => $cmd) $cmdslayout .="<input type=\"button\" value=\"$c\" onclick=\"cmd('$cmd')\" style='cursor: grab;'>";
		// history list 
		foreach(array_slice(array_reverse($history),0,10) as $cmd) $historylayout .= "<p onclick=\"cmd(this.innerHTML)\" class=\"historyelement\">$cmd</p>";
		// schema column layout
		$tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type ='table' AND name NOT LIKE 'sqlite_%';")->fetchAll(),'name');
		foreach($tables as $t){ $schema[$t] = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(),'name');}
		foreach($schema as $t => $columns){
			$schemalayout.='<input type="checkbox" id="collapse-'.$t.'" aria-hidden="true">';
			$schemalayout.='<label for="collapse-'.$t.'" aria-hidden="true">'.$t.'</label>';
			$schemalayout.='<div>';
			$schemalayout.='<i onclick="insert(\''.$t.'\')" style="cursor: grab;">'.$t.' <span class="icon-edit" ></span></i> &bull; <a href="?explore='.$t.'">SELECT</a> &bull; <a href="javascript:duplicate(\''.$t.'\')">DUPLICATE</a> &bull; <a href="?export='.$t.'">EXPORT</a>';
			$schemalayout.='<ul>';
			foreach($columns as $c) $schemalayout.='<li onclick="insert(\''.$c.'\')" style="cursor: grab;">'.$c.' <span class="icon-edit"></span></li>';
			$schemalayout.='</ul>';
			$schemalayout.='</div>';
		}
		// bottom sql layout
		$sqllayout = '
			<div class="row">
				<div class="col-lg-9 col-md-12">
					<form method="post" id="sqlform">
						<fieldset>
							<legend>Execute a SQLITE command</legend>
							'.$cmdslayout.'<br>
							<textarea id="sql" name="sql" placeholder="Your command" required style="width: 100%;height: 20vh;">'.$_POST['sql'].'</textarea><br>
							<input type="submit" value="EXECUTE COMMAND" class="tertiary">
						</fieldset>
						'.$historylayout.'
					</form>
					<script>
						function cmd(sql){
							if(document.getElementById("sql").value === "") document.getElementById("sql").value = sql; 
							else if(confirm("Delete the current request ?")) document.getElementById("sql").value = sql;
						}
						function insert(text){
							let textarea = document.getElementById("sql");
							let val = textarea.value;
							let start = textarea.selectionStart;
							let end = textarea.selectionEnd;
							textarea.value = val.slice(0, start) + text + val.slice(end);
							textarea.focus();
							textarea.setSelectionRange(start, start+text.length);
						}
						function duplicate(table){
							if(confirm("Do you really want to duplicate this table?")){
								let textarea = document.getElementById("sql");
								let form = document.getElementById("sqlform");
								textarea.value = "CREATE TABLE `" + table + "_copy` AS SELECT * FROM `" + table + "`;"
								form.submit();
							}
						}
						function proposeSql(sql){
							let textarea = document.getElementById("sql");
							textarea.value = b64_to_utf8(sql);
						}
						function editThisData(cell,table,primary,column){
							let edit = cell.getAttribute("data-edit");
							let original = cell.getAttribute("data-original");
							if(edit == "false"){
								cell.setAttribute("data-edit","true");
								cell.innerHTML = "<textarea style=\'margin:0;\' data-table=\""+table+"\" onkeyup=\"cancelThisEditDataByEscape(event,this.parentElement)\" data-primary=\""+primary+"\" data-column=\""+column+"\">"+b64_to_utf8(original)+"</textarea>";
							}
						}
						function cancelThisEditDataByEscape(event,cell){
							if(event.key === "Escape") {
								cell.setAttribute("data-edit","false");
								let original = cell.getAttribute("data-original");
								cell.innerHTML = b64_to_utf8(original);
    						}
						}
						function SaveAllEditData(){
							let textareas = document.querySelectorAll("textarea");
							let sql = document.getElementById("sql");
							let sqlform = document.getElementById("sqlform");
							sql.value = "";
							for( var k = 0; k < textareas.length; k++){
								t = textareas[k];
								if(t.hasAttribute("data-column")){
									let column = t.getAttribute("data-column")
									let table = t.getAttribute("data-table")
									let primary = t.getAttribute("data-primary")
									let primaryval = t.parentElement.getAttribute("data-primaryval")
									sql.value = sql.value + "UPDATE " + table + " SET " + column + "=\"" + t.value.replace(/"/g, \'""\') + "\" WHERE " + primary + "=\'" + primaryval + "\';\n"
								}
							}
							sqlform.submit();
						}
						function CancelAllEditData(){
							let textareas = document.querySelectorAll("textarea");
							console.log(textareas)
							console.log(textareas.length)
							for( var k = 0; k < textareas.length; k++){
								t = textareas[k];
								if(t.hasAttribute("data-column")){
									console.log(t);
									let cell =t.parentElement
									cell.setAttribute("data-edit","false");
									let original = cell.getAttribute("data-original");
									cell.innerHTML = b64_to_utf8(original);
								}
							}
						}
						function b64_to_utf8( str ) {
							return decodeURIComponent(escape(window.atob( str )));
						}
					</script>
				</div>
				<div class="col-lg-3 col-md-12">
					<div class="collapse">
						'.$schemalayout.'
					</div>
				</div>
			</div>
		';
		render($toplayout.$resultlayout.$sqllayout);
	}
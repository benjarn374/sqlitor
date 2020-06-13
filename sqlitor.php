<?php
	define('VERSION','v0.1');
	session_start();

	$cmds = array(
		"SELECT" => "SELECT * FROM `table_name` WHERE condition1, condition2 ORDER BY column1;",
		"UPDATE" => "UPDATE `table_name` SET column1 = value1, column2 = value2 WHERE condition1, condition2;",
		"INSERT" => "INSERT INTO `table_name` (column1, column2) VALUES (value1, value2);",
		"CREATE" => "CREATE TABLE `table_name` (column1 INTEGER PRIMARY KEY ASC, column2 TEXT);",
		"DROP" => "DROP TABLE `table_name`;",
		"DELETE" => "DELETE FROM `table_name` WHERE condition1, condition2;"
	);

	function constructArray($datas,$primaryKey = null, $tableName = null){
		$table="<div style='overflow-x:auto;max-width:100%;'>";
		$table.="<table class='striped hoverable' style='max-height:60vh;width:".(count($datas[0])*200)."px;'>";
		$table.="<thead>";
		$table.="<tr>";
		foreach(array_keys($datas[0]) as $column){
			if(empty($tableName) || empty($primaryKey)){
				$table.="<th>".$column."</th>";
			}else{
				$table.="<th>".$column." <a href=\"?explore=$tableName&asc=$column\"><span class=\"icon-upload\" style='transform: rotate(180deg);'></span></a> <a href=\"?explore=$tableName&desc=$column\"><span class=\"icon-upload\"></span></a></th>";
			}
		}
		$table.="</tr>";
		$table.="</thead>";
		$table.="<tbody>";
		foreach($datas as $row){
			$table.="<tr>";
			foreach($row as $column => $value){
				if(empty($tableName) || empty($primaryKey) || $column == $primaryKey){
					$table.="<td data-label=\"$column\" ".(($column==$primaryKey)?"style='font-weight:bold;'":"").">".$value."</td>";
				}else{
					$table.="<td data-label=\"$column\" ".(($column==$primaryKey)?"style='font-weight:bold;'":"")." onclick=\"editThisData(this,'$tableName','$primaryKey','$column')\" data-edit=\"false\"  data-primaryval=\"".$row[$primaryKey]."\" data-original=\"".base64_encode($value)."\">".$value."</td>";
				}
			}
			$table.="</tr>";
		}
		$table.="</tbody>";
		$table.="</table>";
		$table.="</div>";

		return $table;
	}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mini.css/3.0.1/mini-default.min.css">
    <title>Sqlitor</title>
	<style>
		table *:not(th){
			font-size:0.9em;
		}
	</style>
  </head>
  <body>
  	<div class="container">
	  <?php
			if($_POST['action']=='quit'){
				echo "<pre>Session ended. <br><a href=\"sqlitor.php\">Return to database connection</a></pre>";
				session_destroy();
				die();
			}
			if(!empty($_POST['db'])) $_SESSION['db'] = $_POST['db'];
			if(!empty($_POST['sql'])){
				$_SESSION['history'][] = $_POST['sql'];
				$_SESSION['history'] = array_unique($_SESSION['history']);
			}
			extract($_SESSION);
			if($db){
				if(!file_exists($db) && $_POST['open']!='CREATE'){
					echo "<pre>the file does not exist : ".$db." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>";
					session_destroy();
					die();
				}
				if(file_exists($db) && $_POST['open']=='CREATE'){
					echo "<pre>the file already exists : ".$db." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>";
					session_destroy();
					die();
				}
				try{
					$pdo = new PDO('sqlite:'.$db);
					$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
					$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				} catch(Exception $e) {
					echo "<pre>Unable to open the database : ".$e->getMessage()." <br><a href=\"sqlitor.php\">Return to database connection</a></pre>";
					session_destroy();
					die();
				}
			}
		?>
		<?php if(empty($db)){ ?>
			<div class="row">
				<div class="col-lg-4 col-lg-offset-4 col-md-4 col-md-offset-4 col-sm-12">
					<h1>SQLITOR <?= VERSION ?></h1>
					<form method="post">
						<select name="open"><option>OPEN</option><option>CREATE</option></select>
						<input name="db" type="text" placeholder="DB" required>
						<input type="submit" value="GO">
					</form>
				</div>
			</div>
		<?php }else{ ?>
			<form method="post">
				<div class="row">
					<div class="col-lg-6 col-md-6 col-sm-12">
						<h1>> <?= $db ?> </h1>
					</div>
					<div class="col-lg-6 col-md-6 col-sm-12" style='text-align:right;'>
						<input type="hidden" name="action" value="quit"><input class="secondary" type="submit" value="Quit this database">
					</div>
				</div>
			</form>
			<?php 
					if(!empty($_POST['sql'])){
						echo "<div class='row'>";
						echo "<div class='col-lg-12 col-md-12'>";
						$sqls = explode(";",$_POST['sql']);
						$i=0;
						foreach($sqls as $sql){
							$sql = trim($sql);
							if(!empty($sql)){
								echo "<div id='result$i'>";
								try{
									$results = $pdo->query($sql.";");
									if($results){
										$array = $results->fetchAll();
										$affected=$results->rowCount();
										echo "<pre>".$sql.";<br><mark class=\"tertiary\">Request successfully executed</mark>".(($affected>0)?"<br>Affected rows: $affected":"")."</pre>";
										if(count($array)>0) echo constructArray($array);
									}
								} catch(Exception $e) {
									echo "<pre>".$sql.";<br><mark class=\"secondary\" style='word-wrap:anywhere'>".$e->getMessage()."</mark></pre>";
								} 
								echo "<p style='text-align:right;'><button class='small' onclick=\"document.getElementById('result$i').remove();\">ERASE</button></p></div>";
								$i++;
							}
						}
						echo "</div>";
						echo "</div>";
					}elseif(!empty($_GET['explore'])){
						$t = $_GET['explore'];
						if(!empty($_GET['asc'])) $order = " ORDER BY ".$_GET['asc']." ASC";
						if(!empty($_GET['desc'])) $order = " ORDER BY ".$_GET['desc']." DESC";
						echo "<div class='row'>";
						echo "<div class='col-lg-12 col-md-12'>";
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
								echo "<pre>".$sql.";<br>Selected rows: $rowCount</pre>";
								if(empty($_GET['page'])) $_GET['page']=1;
								$array = array_splice($array,($_GET['page']-1)*50,$_GET['page']*50);
								if(count($array)>0) echo constructArray($array,$primary,$t);
							}
						} catch(Exception $e) {
							echo "<pre>".$sql.";<br><mark class=\"secondary\" style='word-wrap:anywhere'>".$e->getMessage()."</mark></pre>";
						}
						echo "<p>";
						$pageCount = ceil($rowCount/50);
						$link = "?explore=$t";
						if(!empty($_GET['asc'])) $link .= "&asc=".$_GET['asc'];
						if(!empty($_GET['desc'])) $link .= "&desc=".$_GET['desc'];
						if($pageCount<20){
							for($i=1;$i<=$pageCount;$i++){
								echo "<a href='$link&page=$i' class=\"button ".(($i==$_GET['page'])?"primary":"")."\">$i</a>";
							}
						}else{
							if($_GET['page']>6) echo "<a href='$link&page=1' class=\"button\"><<</a>";
							for($i=$_GET['page']-5;$i<$_GET['page'];$i++){
								if($i>0) echo "<a href='$link&page=$i' class=\"button ".(($i==$_GET['page'])?"primary":"")."\">$i</a>";
							}
							echo "<a href='$link&page={$_GET['page']}' class=\"button primary\">{$_GET['page']}</a>";
							for($i=$_GET['page']+1;$i<$_GET['page']+6;$i++){
								if($i<=$pageCount) echo "<a href='$link&page=$i' class=\"button ".(($i==$_GET['page'])?"primary":"")."\">$i</a>";
							}
							if($_GET['page']<$pageCount-6) echo "<a href='$link&page=$pageCount' class=\"button\">>></a>";
						}
						echo "<button onclick=\"SaveAllEditData()\" style=\"float:right;\">SAVE ALL</button><button onclick=\"CancelAllEditData()\" style=\"float:right;\">CANCEL ALL</button>";
						echo "</p>";
						echo "</div>";
						echo "</div>";
					}
			?>
			<div class="row">
				<div class="col-lg-9 col-md-12">
					<form method="post" id="sqlform">
						<fieldset>
							<legend>Execute a SQLITE command</legend>
							<?php foreach($cmds as $c => $cmd) echo "<input type=\"button\" value=\"$c\" onclick=\"cmd('$cmd')\" style='cursor: grab;'>"; ?><br>
							<textarea id="sql" name="sql" placeholder="Your command" required style="width: 100%;height: 20vh;"><?= $_POST['sql'] ?></textarea><br>
							<input type="submit" value="EXECUTE COMMAND" class="tertiary">
							<?php foreach(array_slice(array_reverse($history),0,10) as $cmd){
								echo "<p onclick=\"cmd(this.innerHTML)\" style='cursor: grab;'>$cmd</p>";
							}?>
						</fieldset>
					</form>
					<script>
						function cmd(sql){
							if(document.getElementById('sql').value === "") document.getElementById('sql').value = sql; 
							else if(confirm('Delete the current request ?')) document.getElementById('sql').value = sql;
						}
						function insert(text){
							let textarea = document.getElementById('sql');
							let val = textarea.value;
							let start = textarea.selectionStart;
							let end = textarea.selectionEnd;
							textarea.value = val.slice(0, start) + text + val.slice(end);
							textarea.focus();
							textarea.setSelectionRange(start, start+text.length);
						}
						function editThisData(cell,table,primary,column){
							let edit = cell.getAttribute("data-edit");
							let original = cell.getAttribute("data-original");
							if(edit == 'false'){
								cell.setAttribute("data-edit","true");
								cell.innerHTML = "<textarea style='margin:0;' data-table=\""+table+"\" onkeyup=\"cancelThisEditDataByEscape(event,this.parentElement)\" data-primary=\""+primary+"\" data-column=\""+column+"\">"+b64_to_utf8(original)+"</textarea>";
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
									sql.value = sql.value + "UPDATE " + table + " SET " + column + "=\"" + t.value.replace(/"/g, '""') + "\" WHERE " + primary + "='" + primaryval + "';\n"
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
						<?php 
						// schema
						$tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type ='table' AND name NOT LIKE 'sqlite_%';")->fetchAll(),'name');
						foreach($tables as $t){ $schema[$t] = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(),'name');}
						foreach($schema as $t => $columns){ ?>
						<input type="checkbox" id="collapse-<?=$t?>" aria-hidden="true">
						<label for="collapse-<?=$t?>" aria-hidden="true"><?=$t?></label>
						<div>
							<a href="?explore=<?=$t?>">SELECT</a> <i onclick="insert('<?=$t?>')" style='cursor: grab;'><?=$t?> <span class="icon-edit" ></span></i> 
							<ul>
								<?php foreach($columns as $c) echo "<li onclick=\"insert('$t.$c')\" style='cursor: grab;'>$c <span class=\"icon-edit\"></span></li>"; ?>
							</ul>
						</div>
						<?php }?>
					</div>
				</div>
			</div>
		<? } ?>
	</div>
  </body>
</html>


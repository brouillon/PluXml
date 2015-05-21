<?php

/**
 * Gestion des médias
 *
 * @package PLX
 * @author  Stephane F
 **/

include(dirname(__FILE__).'/prepend.php');

# Control du token du formulaire
plxToken::validateFormToken($_POST);
# Sécurisation du chemin du dossier de destination et de visualisation
if(isset($_POST['folder']) AND $_POST['folder']!='.' AND $_POST['folder']!='..' AND !plxUtils::checkSource($_POST['folder'])) {
	$_POST['folder']='.';
}

# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminMediasPrepend'));

# Recherche du type de medias à afficher via la session
if(empty($_SESSION['medias'])) {
	$_SESSION['medias']=$plxAdmin->aConf['medias'];
	$_SESSION['folder']='';
	$_SESSION['currentfolder']='';
}
elseif(!empty($_POST['folder'])) {
	$_SESSION['currentfolder']=(isset($_SESSION['folder'])?$_SESSION['folder']:'');
	if($_POST['folder']=='..')
		$_POST['folder']=preg_replace('#([^\/]*\/)$#', '', $_SESSION['folder']);
	if($_POST['folder']=='' OR $_POST['folder']=='.')
		$_SESSION['folder']=$_POST['folder']='';
	else
		$_SESSION['folder']=rtrim($_POST['folder'], '/\\').DIRECTORY_SEPARATOR;
}

# Nouvel objet de type plxMedias
if($plxAdmin->aConf['userfolders'] AND $_SESSION['profil']==PROFIL_WRITER)
	$plxMedias = new plxMedias(PLX_ROOT.$_SESSION['medias'].$_SESSION['user'].'/',$_SESSION['folder']);
else
	$plxMedias = new plxMedias(PLX_ROOT.$_SESSION['medias'],$_SESSION['folder']);


if(!empty($_POST['btn_newfolder']) AND !empty($_POST['newfolder'])) {
	$newdir = plxUtils::title2filename(trim($_POST['newfolder']));
	if($plxMedias->newDir($newdir)) {
		$_SESSION['folder'] = $_SESSION['folder'].$newdir.DIRECTORY_SEPARATOR;
	}
	header('Location: medias.php');
	exit;
}
elseif(!empty($_POST['btn_upload'])) {
	$plxMedias->uploadFiles($_FILES, $_POST);
	header('Location: medias.php');
	exit;
}
elseif (!empty($_POST['btn_ok']) AND isset($_POST['selection'])) {
	if($_POST['selection']=='delete') {
		if (isset($_POST['idFile']))
			$plxMedias->deleteFiles($_POST['idFile']);
		if (isset($_POST['idDir']))
			$plxMedias->deleteDirs($_POST['idDir']);
		header('Location: medias.php');
		exit;
	}
	elseif ($_POST['selection']=='move') {
		if (isset($_POST['idFile']))
			$plxMedias->moveFiles($_POST['idFile'], $_SESSION['currentfolder'], $_POST['folder']);
		if (isset($_POST['idDir']))
			$plxMedias->moveDirs($_POST['idDir'], $_SESSION['currentfolder'], $_POST['folder']);
		header('Location: medias.php');
		exit;
	}
	elseif ($_POST['selection']=='thumbs') {
		$plxMedias->makeThumbs($_POST['idFile'], $plxAdmin->aConf['miniatures_l'], $plxAdmin->aConf['miniatures_h']);
		header('Location: medias.php');
		exit;
	}
}


# Tri de l'affichage des fichiers
if(isset($_POST['sort']) AND !empty($_POST['sort'])) {
	$sort = $_POST['sort'];
} else {
	$sort = isset($_SESSION['sort_medias']) ? $_SESSION['sort_medias'] : 'title_asc';
}

$sort_title = 'title_desc';
$sort_date = 'date_desc';
switch ($sort) {
	case 'title_asc':
		$sort_title = 'title_desc';
		usort($plxMedias->aFiles, create_function('$b, $a', 'return strcmp($a["name"], $b["name"]);'));
		break;
	case 'title_desc':
		$sort_title = 'title_asc';
		usort($plxMedias->aFiles, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
		break;
	case 'date_asc':
		$sort_date = 'date_desc';
		usort($plxMedias->aFiles, create_function('$b, $a', 'return strcmp($a["date"], $b["date"]);'));
		break;
	case 'date_desc':
		$sort_date = 'date_asc';
		usort($plxMedias->aFiles, create_function('$a, $b', 'return strcmp($a["date"], $b["date"]);'));
		break;
}
$_SESSION['sort_medias']=$sort;

# Contenu des 2 listes déroulantes
$selectionList = array('' =>L_FOR_SELECTION, 'move'=>L_PLXMEDIAS_MOVE_FOLDER, 'thumbs'=>L_MEDIAS_RECREATE_THUMB, '-'=>'-----', 'delete' =>L_DELETE);

# On inclut le header
include(dirname(__FILE__).'/top.php');

?>
<script type="text/javascript" src="<?php echo PLX_CORE ?>lib/multifiles.js"></script>
<script type="text/javascript">
function toggle_divs(){
	var uploader = document.getElementById('files_uploader');
	var manager = document.getElementById('files_manager');
	if(uploader.style.display == 'none') {
		uploader.style.display = 'block';
		manager.style.display = 'none';
	} else {
		uploader.style.display = 'none';
		manager.style.display = 'block';
	}
}
function action_change(){
	var sFolder = document.getElementById('s_folder');
	
	if(this.options[this.selectedIndex].value == 'move') {
		sFolder.style.display = 'inline';
	} else {
		sFolder.display = 'none';
	}
}
</script>


<form action="medias.php" method="post" id="form_medias">

	<div class="inline-form action-bar">
		<h2><?php echo L_MEDIAS_TITLE ?></h2>
		<p>
			<?php echo L_MEDIAS_DIRECTORY.' : / <a title="root" href="javascript:void(0)" onclick="document.forms[0].folder.value=\'\';document.forms[0].submit();return true;">'.plxUtils::strCheck(basename($_SESSION['medias'])).'</a>'?> /
			<?php
			foreach($plxMedias->currentFolder() as $path => $pathName){
				echo '<a title="'.$pathName.'" href="javascript:void(0)" onclick="document.forms[0].folder.value=\''.$path.'\';document.forms[0].submit();return true;">'.$pathName.'</a> / ';
			}
			?>
		</p>
		<?php plxUtils::printSelect('selection', $selectionList, '', false, 'no-margin', 'id_selection') ?>
		<span id="s_folder" style="display:none;">
			&nbsp;&nbsp;<?php echo L_MEDIAS_IN_FOLDER ?>&nbsp;:&nbsp;
			<?php echo $plxMedias->contentFolder() ?>
		</span>
		<input type="submit" name="btn_ok" value="<?php echo L_OK ?>" onclick="return confirmAction(this.form, 'id_selection', 'delete', 'idFile[]', '<?php echo L_CONFIRM_DELETE ?>')" />
		&nbsp;&nbsp;&nbsp;
		<input type="submit" onclick="toggle_divs();return false" value="<?php echo L_MEDIAS_ADD_FILE ?>" />
		<!--<?php if(!empty($_SESSION['folder'])) { ?>
		<input type="submit" name="btn_delete" class="red" value="<?php echo L_DELETE_FOLDER ?>" onclick="return confirm('<?php printf(L_MEDIAS_DELETE_FOLDER_CONFIRM, $curFolder) ?>')" />
		<?php } ?>-->
		<input type="hidden" name="sort" value="" />	
		<input type="hidden" name="folder" value="<?php echo$_SESSION['folder']; ?>" />	
	</div>

	<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasTop')) # Hook Plugins ?>

	<div class="inline-form" id="files_manager">
		<?php echo plxToken::getTokenPostMethod() ?>
		<div>
			<?php echo L_MEDIAS_NEW_FOLDER ?>&nbsp;:&nbsp;
			<input id="id_newfolder" type="text" name="newfolder" value="" maxlength="50" size="10" />
			<input type="submit" name="btn_newfolder" value="<?php echo L_MEDIAS_CREATE_FOLDER ?>" />
		</div>
	
		<div class="scrollable-table">
			<table id="medias-table" class="full-width">
				<thead>
					<tr>
						<th class="checkbox">
							<input type="checkbox" id="checkFile" onclick="checkAll(this.form, 'idFile[]')" />
						</th>
						<th></th>
						<th><a href="javascript:void(0)" class="hcolumn" onclick="document.forms[0].sort.value='<?php echo $sort_title ?>';document.forms[0].submit();return true;"><?php echo L_MEDIAS_FILENAME ?></a></th>
						<th class="infos"><?php echo L_MEDIAS_EXTENSION ?></th>
						<th class="infos"><?php echo L_MEDIAS_FILESIZE ?></th>
						<th class="infos"><?php echo L_MEDIAS_DIMENSIONS ?></th>
						<th class="date"><a href="javascript:void(0)" class="hcolumn" onclick="document.forms[0].sort.value='<?php echo $sort_date ?>';document.forms[0].submit();return true;"><?php echo L_MEDIAS_DATE ?></a></th>
					</tr>
				</thead>
			<tbody>
			<?php
			# Initialisation de l'ordre
			$num = 0;
			# Si ce n'est pas le dossier root
			if ($_SESSION['folder'] != '') {
				$num++;
				echo '<tr class="line-'.($num%2).'">';
				echo '<td class="checkbox center"></td>';
				echo '<td class="icon center"><a title="&crarr; .." href="javascript:void(0)" onclick="document.forms[0].folder.value=\'..\';document.forms[0].submit();return true;"><img src="'.PLX_CORE.'admin/theme/images/folder.png" alt="folder" /></a></td>';
				echo '<td><a title="&crarr; .."  href="javascript:void(0)" onclick="document.forms[0].folder.value=\'..\';document.forms[0].submit();return true;">&crarr;&nbsp;..&nbsp;</a></td>';
				echo '<td>'.L_MEDIAS_FOLDER.'</td>';
				echo '<td></td>';
				echo '<td></td>';
				echo '<td></td>';
				echo '</tr>';
			}
			# Si on a des répertoires
			if($plxMedias->aDirs) {
				$level = false;
				foreach($plxMedias->aDirs as $v) { # Pour chaque dossier

					if ($_SESSION['folder'] == '' && $v['level'] != 0)
						continue;
					if ($v['path'] == $_SESSION['folder']) {
						 $level = $v['level'] + 1;
						continue;
					}
					if ($_SESSION['folder'] != '' AND ($v['path'] == $_SESSION['folder'] OR $v['level'] != $level OR substr($v['path'], 0, strlen($_SESSION['folder'])) != $_SESSION['folder'])) {
						continue;
					}
					$level = $v['level'];
					$num++;
					echo '<tr class="line-'.($num%2).'">';
					echo '<td class="checkbox center"><input type="checkbox" name="idDir[]" value="'.$v['name'].'" /></td>';
					echo '<td class="icon center">';
					echo '<a title="'.plxUtils::strCheck($v['name']).'"  href="javascript:void(0)" onclick="document.forms[0].folder.value=\''.$v['path'].'\';document.forms[0].submit();return true;"><img src="'.PLX_CORE.'admin/theme/images/folder.png" alt="folder"  /></a>';
					echo '</td>';
					echo '<td><a title="'.plxUtils::strCheck($v['name']).'" href="javascript:void(0)" onclick="document.forms[0].folder.value=\''.$v['path'].'\';document.forms[0].submit();return true;">'.plxUtils::strCheck($v['name']).'</a></td>';
					echo '<td>'.L_MEDIAS_FOLDER.'</td>';
					echo '<td></td>';
					echo '<td></td>';
					echo '<td></td>';
					echo '</tr>';
				}
			}
			# Si on a des fichiers
			if($plxMedias->aFiles) {
				foreach($plxMedias->aFiles as $v) { # Pour chaque fichier
					$num++;
					echo '<tr class="line-'.($num%2).'">';
					echo '<td class="checkbox center"><input type="checkbox" name="idFile[]" value="'.$v['name'].'" /></td>';
					echo '<td class="icon center"><a onclick="this.target=\'_blank\';return true;" title="'.plxUtils::strCheck($v['name']).'" href="'.$v['path'].'"><img alt="" src="'.$v['.thumb'].'" class="thumb" /></a><br /></td>';
					echo '</td>';
					echo '<td>';
					echo '<a onclick="this.target=\'_blank\';return true;" title="'.plxUtils::strCheck($v['name']).'" href="'.$v['path'].'">'.plxUtils::strCheck($v['name']).'</a><br />';
					if($v['thumb']) {
						echo '<a onclick="this.target=\'_blank\';return true;" title="'.L_MEDIAS_THUMB.' : '.plxUtils::strCheck($v['name']).'" href="'.plxUtils::thumbName($v['path']).'">'.L_MEDIAS_THUMB.'</a> : '.$v['thumb']['infos'][0].' x '.$v['thumb']['infos'][1]. ' ('.plxUtils::formatFilesize($v['thumb']['filesize']).')';
					}
					echo '</td>';
					echo '<td>'.strtoupper($v['extension']).'</td>';
					echo '<td>'.plxUtils::formatFilesize($v['filesize']).'</td>';
					$dimensions = '&nbsp;';
					if(isset($v['infos']) AND isset($v['infos'][0]) AND isset($v['infos'][1])) {
						$dimensions = $v['infos'][0].' x '.$v['infos'][1];
					}
					echo '<td>'.$dimensions.'</td>';
					echo '<td>'.plxDate::formatDate(plxDate::timestamp2Date($v['date'])).'</td>';
					echo '</tr>';
				}
			}
			else echo '<tr><td colspan="7" class="center">'.L_MEDIAS_NO_FILE.'</td></tr>';
			?>
			</tbody>
			</table>
		</div>
	</div>
		
</form>
	
<form action="medias.php" method="post" id="form_uploader" class="form_uploader" enctype="multipart/form-data">

	<div id="files_uploader" style="display:none">

		<p id="medias_back"><a href="javascript:void(0)" onclick="toggle_divs();return false"><?php echo L_MEDIAS_BACK ?></a></p>

		<p><?php echo L_MEDIAS_MAX_UPOLAD_FILE ?> : <?php echo $plxMedias->maxUpload['display'] ?></p>
		<div class="inline-form">
			<input id="selector" type="file" name="selector" />
			<input type="submit" name="btn_upload" id="btn_upload" value="<?php echo L_MEDIAS_SUBMIT_FILE ?>" />
		</div>
		<div class="files_list" id="files_list" style="margin-top: 1rem;">
		</div>
		<div class="grid">
			<div class="col sma-12 med-4">
				<ul class="unstyled-list">
					<li><?php echo L_MEDIAS_RESIZE ?>&nbsp;:&nbsp;</li>
					<li><input type="radio" name="resize" value="" />&nbsp;<?php echo L_MEDIAS_RESIZE_NO ?></li>
					<?php
						foreach($img_redim as $redim) {
							echo '<li><input type="radio" name="resize" value="'.$redim.'" />&nbsp;'.$redim.'</li>';
						}
					?>
					<li>
						<input type="radio" checked="checked" name="resize" value="<?php echo intval($plxAdmin->aConf['images_l' ]).'x'.intval($plxAdmin->aConf['images_h' ]) ?>" />&nbsp;<?php echo intval($plxAdmin->aConf['images_l' ]).'x'.intval($plxAdmin->aConf['images_h' ]) ?>
						&nbsp;&nbsp;(<a href="parametres_affichage.php"><?php echo L_MEDIAS_MODIFY ?>)</a>
					</li>
					<li>
						<input type="radio" name="resize" value="user" />&nbsp;
						<input type="text" size="2" maxlength="4" name="user_w" />&nbsp;x&nbsp;
						<input type="text" size="2" maxlength="4" name="user_h" />
					</li>
				</ul>
			</div>
			<div class="col sma-12 med-8">
				<ul class="unstyled-list">
					<li><?php echo L_MEDIAS_THUMBS ?>&nbsp;:&nbsp;</li>
					<li>
						<?php $sel = (!$plxAdmin->aConf['thumbs'] ? ' checked="checked"' : '') ?>
						<input<?php echo $sel ?> type="radio" name="thumb" value="" />&nbsp;<?php echo L_MEDIAS_THUMBS_NONE ?>
					</li>
					<?php
						foreach($img_thumb as $thumb) {
							echo '<li><input type="radio" name="thumb" value="'.$thumb.'" />&nbsp;'.$thumb.'</li>';
						}
					?>
					<li>
						<?php $sel = ($plxAdmin->aConf['thumbs'] ? ' checked="checked"' : '') ?>
						<input<?php echo $sel ?> type="radio" name="thumb" value="<?php echo intval($plxAdmin->aConf['miniatures_l' ]).'x'.intval($plxAdmin->aConf['miniatures_h' ]) ?>" />&nbsp;<?php echo intval($plxAdmin->aConf['miniatures_l' ]).'x'.intval($plxAdmin->aConf['miniatures_h' ]) ?>
						&nbsp;&nbsp;(<a href="parametres_affichage.php"><?php echo L_MEDIAS_MODIFY ?>)</a>
					</li>
					<li>
						<input type="radio" name="thumb" value="user" />&nbsp;
						<input type="text" size="2" maxlength="4" name="thumb_w" />&nbsp;x&nbsp;
						<input type="text" size="2" maxlength="4" name="thumb_h" />
					</li>
				</ul>
			</div>
		</div>
		<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasUpload')) # Hook Plugins ?>
		<?php echo plxToken::getTokenPostMethod() ?>
		<script type="text/javascript">
			var multi_selector = new MultiSelector(document.getElementById('files_list'), -1, '<?php echo $plxAdmin->aConf['racine'] ?>');
			multi_selector.addElement(document.getElementById('selector'));
		</script>
	</div>

</form>
</div>
<script type="text/javascript">
	document.getElementById('id_selection').onchange = action_change;
	document.getElementById('folder_list').onchange = function () {
		document.forms[0].folder.value=this.options[this.selectedIndex].value;
	};
</script>
<?php
# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminMediasFoot'));
# On inclut le footer
include(dirname(__FILE__).'/foot.php');
?>
<?

class FrontController extends Controller{
	
	private static $_instance = null;
	
	public $requestMethod = null;
	public $requestParams = array();
	
	/** контейнер обмена данными между методами */
	public $data = array();
	
	public static function get(){
		
		if(is_null(self::$_instance))
			self::$_instance = new FrontController();
		
		return self::$_instance;
	}
	
	public function __construct(){
		
		$this->_checkAuth();
		
		$request = explode('/', getVar($_GET['r']));
		$_rMethod = array_shift($request);
		
		$this->requestMethod = !empty($_rMethod) ? $_rMethod : 'index';
		$this->requestParams = $request;
		
		if (!empty($_POST))
			$_POST = unescapeArr($_POST);
	}
	
	public function run(){
		
		$this->_checkAction();
		$this->_checkDisplay();
	}
	
	public function ajax(){
		
		if($this->_checkAction())
			exit;
			
		$this->_checkAjax();
	}
	
	private function _checkAuth(){
		
		if(getVar($_POST['action']) == 'login')
			$this->action_login();
		
		if(empty($_SESSION['su']['logged']))
			$this->display_login();
	}
	
	private function _checkAction(){
		
		if(isset($_POST['action']) && checkFormDuplication()){
			
			$action = $_POST['action'];
			$method = $this->getActionMethodName($action);
			
			if(!method_exists($this, $method))
				$this->display_404($method);
				
			if($this->$method())
				if(!empty($_POST['redirect']))
					redirect($_POST['redirect']);
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	private function _checkDisplay(){
		
		$method = $this->getDisplayMethodName($this->requestMethod);
		
		if(!method_exists($this, $method))
			$this->display_404($method);
		
		$this->$method($this->requestParams);
	}
	
	private function _checkAjax(){
		
		$method = $this->getAjaxMethodName($this->requestMethod);
		
		if(!method_exists($this, $method))
			$this->display_404($method);
		
		$this->$method($this->requestParams);
	}
	
	/**
	 * ПОЛУЧИТЬ ИМЯ КЛАССА КОНТРОЛЛЕРА ПО ИДЕНТИФИКАТОРУ
	 * @param string $controllerIdentifier - идентификатор контроллера
	 * @return string|null - имя класса  контроллера или null, если контроллер не найден
	 */
	public static function getControllerClassName($controllerIdentifier){
			
		// если идентификатор контроллера не передан, вернем null
		if(empty($controllerIdentifier))
			return null;
		
		// если идентификатор контроллера содержит недопустимые символы, вернем null
		if(!preg_match('/^[\w\-]$/', $controllerIdentifier))
			return null;
			
		// преобразует строку вида 'any-class-name' в 'AnyClassNameController'
		$controller = str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($controllerIdentifier)))).'Controller';
		return class_exists($controller) ? $controller : null;
	}
	
	
	/////////////////////
	////// DISPLAY //////
	/////////////////////
	
	public function display_index(){
		
		Layout::get()
			->setContentPhpFile('index.php')
			->render();
	}
	
	public function display_globals(){
		
		Layout::get()
			->setContentPhpFile('globals.php')
			->render();
	}
	
	public function display_phpinfo(){
		
		phpinfo();
		exit;
	}
	
	public function display_file_manager(){
		
		Layout::get()
			->setTitle('Файловый менеджер')
			->setContentPhpFile('file-manager.php')
			->render();
	}
	
	public function display_login(){
		
		Layout::get()->loginPage();
	}
	
	public function display_clear_session(){
		$_SESSION = array();
		redirect('');
	}
	
	public function display_404($method = ''){
		
		if(AJAX_MODE){
			echo 'Страница не найдена ('.$method.')';
		}else{
			Layout::get()
				->setContent('<h1 style="text-align: center;">Страница не найдена</h1> ('.$method.')')
				->render();
		}
		exit;
	}
	
	public function display_fm_upload(){
		
		$panel = getVar($_GET['panel']);
		$message = getVar($this->data['message']);
		include(FS_ROOT.'templates/fm-upload.php');
		
		// if(!empty($this->data['message'])){
			// echo '<pre>';
			// print_r($_POST);
			// print_r($_FILES);
		// }
	}
	
	public function display_fm_openfile(){
		
		$_filename = unescape(getVar($_GET['f']));
		$filename = WIN_SERVER ? utf2ansi($_filename) : $_filename;
		if(!file_exists($filename))
			exit('Файл '.$filename.' не найден.');
		if(!is_readable($filename))
			exit('Невозможно прочитать файл '.$filename.'. Нет прав на чтение.');
		
		header('Content-type: text/plain');
		header('Content-Disposition: inline; filename='.basename($filename));
		readfile($filename);
		exit;
	}
	
	public function display_fm_editfile(){
		
		$_filename = unescape(getVar($_GET['f']));
		$filename = WIN_SERVER ? utf2ansi($_filename) : $_filename;
		if(!file_exists($filename))
			exit('Файл '.$filename.' не найден.');
		if(!is_readable($filename))
			exit('Невозможно прочитать файл '.$filename.'. Нет прав на чтение.');
			
		$filecontent = file_get_contents($filename);
		include(FS_ROOT.'templates/fm-editfile.php');
	}
	
	public function display_fm_download(){
		
		$_filename = unescape(getVar($_GET['f']));
		$filename = WIN_SERVER ? utf2ansi($_filename) : $_filename;
		if(!file_exists($filename))
			exit('Файл '.$filename.' не найден.');
		if(!is_readable($filename))
			exit('Невозможно прочитать файл '.$filename.'. Нет прав на чтение.');
		
		header('Expires: 0');
		header('Cache-Control: private');
		header('Pragma: cache');
		header('Content-type: application/download');
		header('Content-Disposition: attachment; filename='.basename($filename));
		header('Content-Disposition: inline; filename='.basename($filename));
		readfile($filename);
		exit;
	}
	
	////////////////////
	////// ACTION //////
	////////////////////
	
	public function action_login(){
		
		if (sha1(getVar($_POST['login'])) == '53fb7ec2b04bbeecd8bc0902b217fb0b03165fde' &&
			sha1(getVar($_POST['pass'])) == 'c776f7b86a4701a3e3a94c253901006cf31e6d32'
		){
			$_SESSION['su']['logged'] = 1;
			reload();
		}
	}
	
	public function action_logout(){
		
		$_SESSION['su']['logged'] = 0;
		reload();
	}
	
	public function action_fm_save_file(){
		
		$_filename = getVar($_POST['file-name']);
		$filename = WIN_SERVER ? utf2ansi($_filename) : $_filename;
		$fileContent = getVar($_POST['file-content']);
		
		if(!file_exists($filename))
			exit('Файл '.$filename.' не найден.');
		if(!is_writeable($filename))
			exit('Невозможно сохранить файл '.$filename.'. Нет прав на запись.');
		if(preg_match('/^\s*$/', $fileContent) && empty($_POST['allowEmpty']))
			exit('Попытка сохранить пустой файл без разрешения');
		
		file_put_contents($filename, $fileContent);
		echo 'ok';
	}
	
	public function action_fm_upload(){
		
		$this->data['message'] = '';
		
		$numUploadedFiles = 0;
		$dir = getVar($_POST['dir']);
		
		if(!is_dir($dir)){
			$this->data['message'] = 'ОШИБКА!\n"'.$dir.'" не является директорией';
			return false;
		}
		
		if(!is_writeable($dir)){
			$this->data['message'] = 'ОШИБКА!\nдиректория "'.$dir.'" не доступна для записи';
			return false;
		}
		
		if(substr($dir, -1) != DIRECTORY_SEPARATOR)
			$dir .= DIRECTORY_SEPARATOR;
		
		// echo $dir; die;
		
		foreach((array)$_FILES['files']['tmp_name'] as $index => $file){
			if(empty($file))
				continue;
			$fname = $_FILES['files']['name'][$index];
			if(file_exists($file)){
				move_uploaded_file($file, $dir.$fname);
				$this->data['message'] .= 'Файл "'.$fname.'" загружен\n';
			}else{
				$this->data['message'] .= 'не удалось загрузить файл "'.$fname.'"\n';
			}
		}
		return true;
	}

	////////////////////
	//////  AJAX  //////
	////////////////////
	
	public function ajax_fm_get_tree(){
		
		$data = array(
			'errcode' => 0,
			'errmsg' => '',
			'curDir' => null,
			'curIsWriteable' => false,
			'dirs' => array(),
			'files' => array(),
			'freeSpace' => '',
			'debug' => '',
		);
		
		$_curDir = unescape(getVar($_GET['dir']));
		$curDir = realpath(WIN_SERVER ? utf2ansi($_curDir) : $_curDir);
		if(substr($curDir, -1) != DIRECTORY_SEPARATOR)
			$curDir .= DIRECTORY_SEPARATOR;
			
		if(!is_dir($curDir)){
			$data['errcode'] = 1;
			$data['errmsg'] = 'Папка "'.$curDir.'" не найдена';
			echo json_encode($data);
			return;
		}
		if(!is_readable($curDir)){
			$data['errcode'] = 2;
			$data['errmsg'] = 'Нет прав на чтение папки';
			echo json_encode($data);
			return;
		}
		
		$data['curDir'] = WIN_SERVER ? ansi2utf($curDir) : $curDir;
		$data['curIsWriteable'] = is_writeable($curDir);
		$data['freeSpace'] = sizeFormat(disk_free_space($curDir));
		
		foreach(scandir($curDir) as $elm){
			
			if($elm == '.' || $elm == '..')
				continue;
			
			$fullName = $curDir.'/'.$elm;
			$isDir = is_dir($fullName);
			// $owner = posix_getpwuid(fileowner($fullName));
			$data[$isDir ? 'dirs' : 'files'][] = array(
				'name' => WIN_SERVER ? ansi2utf($elm) : $elm,
				'perms' => substr(sprintf('%o', @fileperms($fullName)), -3),
				// 'owner' => $owner['name'],
				'size' => $isDir ? '-' : sizeFormat(@filesize($fullName)),
				'emtime' => strDate(@filemtime($fullName)),
			);
		}
		
		echo json_encode($data);
		return;
	}
	
	public function ajax_fm_delete(){
		
		$numDeleted = 0;
		$undeleted = array();
		foreach (getVar($_POST['files'], array(), 'array') as $f) {
			$f = WIN_SERVER ? utf2ansi($f) : $f;
			if (!file_exists($f)) {
				$undeleted[] = $f.' [файл не найден]';
				continue;
			}
			if (!is_writeable($f)) {
				$undeleted[] = $f.' [нет прав на удаление]';
				continue;
			}
			
			$ans = $this->model_fm_delete($f);
			if ($ans == 'ok') {
				$numDeleted++;
			} else {
				$undeleted[] = $ans;
			}
		}
		
		echo count($undeleted)
			? "Удалено $numDeleted файлов\n\nНе удалось удалить:\n".implode("\n", $undeleted)
			: 'ok';
	}
	
	public function ajax_fm_rename(){
		
		$path   = getVar($_POST['path']);
		$origin = $path.getVar($_POST['originName']);
		$new    = $path.getVar($_POST['newName']);
		$isDir  = getVar($_POST['type']) == 'dir';
		
		if (WIN_SERVER) {
			$origin = utf2ansi($origin);
			$new = utf2ansi($new);
		}
		
		if (!file_exists($origin))
			die("Файл $origin не найден");
		
		// if (file_exists($new) && is_dir($new) == $isDir)
		if (file_exists($new))
			die((is_dir($new) ? 'Папка' : 'Файл')." $new уже существует");
		
		if (rename($origin, $new))
			echo 'ok';
		else
			echo ' не удалось переименовать файл';
	}
	
	public function ajax_fm_mkdir(){
		
		$path = getVar($_POST['path']);
		$name = getVar($_POST['name']);
		
		if (!is_dir($path))
			die('Целевая папка не найдена');
		
		if (!preg_match('/^[^\/\\\\*?"<>|:]{1,255}$/', $name))
			die('Недопустимое имя папки "'.$name.'"');
		
		chdir($path);
		
		if (is_dir($name))
			die('Папка с таким именем уже существует');
			
		mkdir(WIN_SERVER ? utf2ansi($name) : $name, 0777, TRUE);
		echo 'ok';
	}
	
	public function ajax_fm_mkfile(){
		
		$path = getVar($_POST['path']);
		$name = getVar($_POST['name']);
		
		if (!is_dir($path))
			die('Целевая папка не найдена');
		
		if (!preg_match('/^[^\/\\\\*?"<>|:]{1,255}$/', $name))
			die('Недопустимое имя файла "'.$name.'"');
		
		chdir($path);
		
		if (file_exists($name) && !is_dir($name))
			die('Файл с таким именем уже существует');
		
		$name = WIN_SERVER ? utf2ansi($name) : $name;
		
		$f = fopen($name, "w") or die('Не удалось создать файл');
		fclose($f);		
		echo 'ok';
	}

	////////////////////
	//////  MODEL  /////
	////////////////////
	
	public function model_fm_delete($file){
		
		if (!is_writeable($file))
			return 'Нет прав на удаление файла '.$file;
		
		if (is_dir($file)) {
			$error = '';
			$file = substr($file, -1) == DIRECTORY_SEPARATOR ? $file : $file.DIRECTORY_SEPARATOR;
			foreach (scandir($file) as $f)
				if ($f != '.' && $f != '..')
					if(($ans = $this->model_fm_delete($file.$f)) != 'ok')
						$error .= $ans;
			if($error)
				return $error;
			else
				return rmdir($file) ? 'ok' : 'не удалось удалить директорию '.$file;
		}else{
			return unlink($file) ? 'ok' : 'не удалось удалить файл '.$file;
		}
		return 'ok';
	}

}

?>
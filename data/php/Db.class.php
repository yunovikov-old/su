<?class db{ 		/**	 * экземпляры класса db (один экземпляр - одно подключение)	 * @var array()	 */	private static $_instances = array();		/**	 * экземпляр дефолтного соединения с БД	 * @var null|db	 */	private static $_defaultInstance = null;			/**	 * СОЗДАНИЕ ПОДКЛЮЧЕНИЯ К БД	 * создает новый экземпляр соединения с бд, при этом не подключаясь к ней.	 * @param array $connParams	 * 		string 'adapter' optional, указывает адаптер подключения к БД	 * 			Если не указан, используется mysql	 *		string 'host' required	 *		string 'user' required	 *		string 'pass' required	 *		string 'database' required	 *		string 'encoding' optional, устанавливает кодировку соединения	 * @param null|string $connIdentifier - идентификатор соединения с БД.	 * 		Если null, создается дефолтное соединение.	 * @return db instance	 */	public static function create($connParams, $connIdentifier = null){				// проверка, переданы ли параметры в виде массива		if(!is_array($connParams))			trigger_error('Параметры соединения с БД должны быть переданы в виде массива.', E_USER_ERROR);					// проверка, введены ли обязательные атрибуты		foreach(array('host', 'user', 'pass', 'database') as $key)			if(!array_key_exists($key, $connParams))				trigger_error('Для подключения к БД требуется указать параметр "'.$key.'"', E_USER_ERROR);				// определение адаптера. по умолчанию используется mysql		$adapter = isset($connParams['adapter'])			? $connParams['adapter']			: 'mysql';		// создание экземпляра класса db		$adapterClass = 'DbAdapter_'.$adapter;		$db = new $adapterClass($connParams['host'], $connParams['user'], $connParams['pass'], $connParams['database']);				if(!empty($connParams['encoding']))			$db->setEncoding($connParams['encoding']);				if(!empty($connParams['keepFileLog']))			$db->keepFileLog($connParams['keepFileLog']);				// если идентификатор соединения не передан		// создаем дефолтное подключение		if(is_null($connIdentifier)){						if(is_null(self::$_defaultInstance))				self::$_defaultInstance = & $db;			else				trigger_error('Соединение с БД с дефолтным идентификатором уже создано', E_USER_ERROR);		}		// если идентификатор соединения указан		// создаем подключение с указанным идентификатором		else{						if(strlen($connIdentifier)){				if(empty(self::$_instances[$connIdentifier]))					self::$_instances[$connIdentifier] = & $db;				else					trigger_error('Соединение с БД с идентификатором "'.$connIdentifier.'" уже создано', E_USER_ERROR);			}else{				trigger_error('Идентификатор соединения с БД должен быть числом, строкой или значением null', E_USER_ERROR);			}		}				return $db;	}		/**	 * ПОЛУЧИТЬ ЭКЗЕМПЛЯР КЛАССА db	 * 	 * @param null|string $connIdentifier - идентификатор соединения с БД.	 * 		Если не указан, возвращается дефолтное соединение.	 * @return instance of db	 */	public static function get($connIdentifier = null){				$db = is_null($connIdentifier)			? self::$_defaultInstance			: (isset(self::$_instances[$connIdentifier])				? self::$_instances[$connIdentifier]				: null);				if(is_null($db))			trigger_error('Соединение с БД с '.(is_null($connIdentifier) ? 'дефолтным идентификатором' : 'идентификатором "'.$connIdentifier.'"').' не создано', E_USER_ERROR);				if(!$db->isConnected())			$db->connect();				return $db;	}	}abstract class DbAdapter{		// флаг, что соединение установлено	protected $_connected = FALSE;		// флаг о необходимости логирования sql в файл	protected $_keepFileLog = FALSE;		// массив сохраненных SQL запросов	protected $_sqls = array();		// время выполнения каждого запроса	protected $_queriesTime = array();	// число выполненных запросов	protected $_queriesNum = 0;		// массив сохранения сообщений об ошибках	protected $_error = array();		// режим накопления сообщений об ошибках	protected $_errorHandlingMode = FALSE;		/**	 * пользовательский обработчик ошибок	 * если null, то ошибки складываются в стандартный контейнер (setError, hasError, getError)	 * @var null|callback	 */	private $_errorHandler = null;		// ресурс соединения с базой данных	protected $_dbrs = null;		// параметры подключения к БД	protected $connHost = '';	protected $connUser = '';	protected $connPass = '';	protected $connDatabase = '';		// дополнительные параметры	protected $_encoding = 'utf8';		abstract public function connect();	abstract public function setEncoding($encoding);	abstract public function getLastId();	abstract public function getAffectedNum();	abstract public function query($query);	abstract public function getOne($query, $default_value = null);	abstract public function getCell($query, $row, $column, $default_value = 0);	abstract public function getCol($query, $default_value = array());	abstract public function getColIndexed($query, $default_value = 0);	abstract public function getRow($query, $default_value = array());	abstract public function getAll($query, $default_value = array());	abstract public function getAllIndexed($query, $index, $default_value = 0);	abstract public function escape($str);	/**	 * ЗАКЛЮЧЕНИЕ ИМЕН ПОЛЕЙ В НУЖНЫЙ ТИП КОВЫЧЕК	 * метод индивидуален для каждого db-адапрета	 * @param variant $field - строка имени поля	 * @return string заключенная в нужный тип ковычек строка	 */	public function quoteFieldName($field){}	abstract public function describe($table);	abstract public function showTables();	abstract public function showCreateTable($table);		// КОНСТРУКТОР	public function __construct($host, $user, $pass, $database){				$this->connHost = $host;		$this->connUser = $user;		$this->connPass = $pass;		$this->connDatabase = $database;	}		// ВКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК	public function enableErrorHandlingMode(){		$this->_errorHandlingMode = TRUE;	}		// ОТКЛЮЧИТЬ РЕЖИМ ОТЛОВА ОШИБОК	public function disableErrorHandlingMode(){		$this->_errorHandlingMode = TRUE;	}		/**	 * УСТАНОВИТЬ ОБРАБОТЧИК ОШИБОК	 * @param null|callback $handler - функция обработки ошибок	 * @return void	 */	public function setErrorHandler($handler){		$this->_errorHandlingMode = !is_null($handler);		$this->_errorHandler = $handler;	}		// ВЫПОЛНЕНО ЛИ ПОДКЛЮЧЕНИЕ К БД	public function isConnected(){				return $this->_connected;	}		// KEEP FILE LOG	public function keepFileLog($boolEnable){				$this->_keepFileLog = $boolEnable;	}	// функция INSERT	public function insert($table, $fieldsValues){				$fields = array();		$values = array();				foreach($fieldsValues as $field => $value){			$fields[] = $this->quoteFieldName($field);			$values[] = $this->qe($value);		}				$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';		$this->query($sql);		return $this->getLastId();	}	// INSERT MULTI	public function insertMulti($table, $fields, $valuesArrArr){				$valuesArrStr = array();		foreach($fields as &$field)			$field = $this->quoteFieldName($field);		foreach($valuesArrArr as $_rowArr){			$rowArr = array();			foreach($_rowArr as $cell)				$rowArr[] = $this->qe($cell);			$valuesArrStr[] = '('.implode(',', $rowArr).')';		}		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES '.implode(',', $valuesArrStr);		return $this->getOne($sql);	}		// UPDATE	public function update($table, $fieldsValues, $conditions) {				$update_arr = array();		foreach($fieldsValues as $field => $value)			$update_arr[] = $this->quoteFieldName($field).'='.$this->qe($value);		$update_str = implode(',',$update_arr);				$conditions = trim(str_replace('WHERE', '', $conditions));		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';			if(!strlen($conditions))			trigger_error('Функции update не передано условие', E_USER_ERROR);				$sql = 'UPDATE '.$table.' SET '.$update_str.$conditions;		$this->query($sql);		return $this->getAffectedNum();	}	/**	 * UPDATE INSERT	 * обновляет информацию в таблице.	 * Если не было обновлено ни одной строки, создает новую строку.	 * Возвращает 0, если было произведено обновление существующей строки,	 * Возвращает id, если была произведена вставка новой строки	 * ВАЖНО: при создании новой строки, функция заполнит ее данными из $fieldsValues и $conditionArr	 * @param string $table - имя таблицы	 * @param array $fieldsValues - поля для обновление	 * @param array $conditionFieldsValues - поля, задающие условие обновления	 * @return int количество затронутых строк	 */	public function updateInsert($table, $fieldsValues, $conditionFieldsValues){				$update_arr = array();		foreach($fieldsValues as $field => $value)			$update_arr[] = $this->quoteFieldName($field).'='.$this->qe($value);				if(!is_array($conditionFieldsValues) || !count($conditionFieldsValues)){			$this->error('функция updateInsert получила неверное условие conditionFieldsValues');			return false;		}		$conditionArr = array();		foreach($conditionFieldsValues as $field => $value)			$conditionArr[] = $this->quoteFieldName($field).'='.$this->qe($value);				$sql = 'UPDATE '.$table.' SET '.implode(',',$update_arr).' WHERE '.implode(' AND ',$conditionArr);		$this->query($sql);				$affected = $this->getAffectedNum();				// если не было изменено ни одной строки, смотрим внимательно		if($affected == 0){			// если такая запись присутствует в таблице, то все хорошо			if($this->getOne('SELECT COUNT(1) FROM '.$table.' WHERE '.implode(' AND ',$conditionArr), 0))				return 0;			// если же нет, то создаем ее			else				return $this->insert($table, array_merge($fieldsValues, $conditionFieldsValues));		}		// если строки были изменены, значит такая запись уже присутствует в таблице		else{			return 0;		}	}	// функция DELETE	public function delete($table, $conditions) {				$conditions = trim(str_replace('WHERE', '', $conditions));		$conditions = strlen($conditions) ? ' WHERE '.$conditions : '';			if(!strlen($conditions))			trigger_error('Функции delete не передано условие. Необходимо использовать truncate', E_USER_ERROR);				$sql = 'DELETE FROM '.$table.$conditions;		$this->query($sql);		return $this->getAffectedNum();	}		/**	 * ЗАКЛЮЧЕНИЕ СТРОК В КОВЫЧКИ	 * в зависимости от типа данных	 * @param variant $cell - исходная строка	 * @return string заключенная в нужный тип ковычек строка	 */	public function quote($cell){				switch(strtolower(gettype($cell))){			case 'boolean':				return $cell ? 'TRUE' : 'FALSE';			case 'null':				return 'NULL';			default:				return "'".$cell."'";		}	}		/**	 * ЭСКЕЙПИРОВАНИЕ И ЗАКЛЮЧЕНИЕ СТРОКИ В КОВЫЧКИ	 * замена последовательному вызову функций db::escape и db::quote	 * @param variant $cell - исходная строка	 * @return string эскейпированая и заключенная в нужный тип ковычек строка	 */	public function qe($cell){				return $this->quote($this->escape($cell));	}		// СОХРАНИТЬ ЗАПРОС	protected function _saveQuery($sql){				$this->_sqls[] = $sql;	}		// СОХРАНИТЬ ВРЕМЯ ИСПОЛНЕНИЯ ЗАПРОСА	protected function _saveQueryTime($t){			$this->_queriesTime[] = $t;	}		// ПОЛУЧИТЬ ЧИСЛО ВЫПОЛНЕННЫХ SQL ЗАПРОСОВ	public function getQueriesNum(){				return $this->_queriesNum;	}		// ПОЛУЧИТЬ ВЫПОЛНЕННЫЕ SQL ЗАПРОСЫ	public function getQueries(){				return $this->_sqls;	}		// ПОЛУЧИТЬ ОБЩЕЕ ВРЕМЯ ВЫПОЛНЕНИЯ SQL ЗАПРОСОВ	public function getQueriesTime(){				return array_sum($this->_queriesTime);	}		// ПОЛУЧИТЬ АССОЦИАТИВНЫЙ МАССИВ ЗАПРОС + ВРЕМЯ	public function getQueriesWithTime(){				$output = array();		foreach($this->_sqls as $index => $sql)			$output[] = array(				'sql' => $sql,				'time' => isset($this->_queriesTime[$index]) ? $this->_queriesTime[$index] : '-'			);		return $output;	}			/**	 * ПЕРЕХВАТ ОШИБОК ВЫПОЛНЕНИЯ SQL-ЗАПРОСОВ	 * Дальнейший путь ошибки зависит от установки _errorHandlingMode	 * @param string $msg - сообщение, сгенерированное СУБД	 * @param string $sql - SQL-запрос, в котором возникла ошибка	 * @return void	 */	protected function error($msg, $sql = ''){			$fullmsg = ""			."\n\nError on ".date('Y-m-d H:i:s')."\n"			."[  SQL] ".str_repeat('-', 80)."\n\n"			.$sql			."\n\n[ERROR] ".str_repeat('-', 80)."\n\n"			.$msg			."\n\n--------".str_repeat('-', 80)."\n\n";				// улавливание ошибок		if($this->_errorHandlingMode){						if(!is_null($this->_errorHandler))				call_user_func($this->_errorHandler, $msg, $sql, $fullmsg);			else				$this->setError($fullmsg);					}		// выброс ошибок		else{					if(PHP_SAPI != 'cli')				$fullmsg = '<pre>'.$fullmsg.'</pre>';						trigger_error($fullmsg, E_USER_ERROR);		}	}		// СОХРАНИТЬ ОШИБКУ	public function setError($error){		$this->_error[] = $error;	}		// ПОЛУЧИТЬ ВСЕ ОШИБКИ	public function getError(){		return implode('<br />', $this->_error);	}		// ПРОВЕРИТЬ, ЕСТЬ ЛИ ОШИБКИ	public function hasError(){		return (bool)count($this->_error);	}		// ЗАГРУЗИТЬ ДАМП ДАННЫХ	public function loadDump($fileName){			if(!$fileName){			$this->setError('Файл дампа не загружен');			return FALSE;		}				if(!file_exists($fileName)){			$this->setError('Файл дампа не найден');			return FALSE;		}				$singleQuery = '';		$numCommands = 0;		$completeCommands = 0;		$failedCommands = 0;				$rs = fopen($fileName, "r");		while(!feof($rs)){					$row = fgets($rs);			$singleQuery .= $row;						if(substr($row, -2) == ";\n"){				try{					$this->query($singleQuery);					$completeCommands++;				}				catch(Exception $e){					echo 'error: '.$e->getMessage().'<br />';					$failedCommands++;				}				$singleQuery = '';				$numCommands++;			}		}		fclose($rs);		return TRUE;	}		// ДЕСТРУКОТР	public function __destruct(){				if($this->_keepFileLog){			$f = fopen(FS_ROOT.'logs/mysql.log', 'a');			fwrite($f, '-------- '.date('Y-m-d H:i:s')." --------\n");			fwrite($f, implode("\n", $this->_sqls));			fwrite($f, "\n\n");			fclose($f);		}	}}class DbAdapter_mysql extends DbAdapter{ 		// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ	public function connect(){			$this->_dbrs = mysql_connect($this->connHost, $this->connUser, $this->connPass) or $this->error('Невозможно подключиться к серверу MySQL');		mysql_select_db($this->connDatabase, $this->_dbrs)or $this->error('Невозможно выбрать базу данных');				if(!empty($this->_encoding))			$this->query('SET NAMES '.$this->_encoding);			$this->_connected = TRUE;	}		// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ	public function setEncoding($encoding){				$this->_encoding = $encoding;				if($this->isConnected())			$this->query('SET NAMES '.$this->_encoding);	}		// ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY	public function getLastId(){			return mysql_insert_id($this->_dbrs);	}		// ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ	public function getAffectedNum(){				return mysql_affected_rows($this->_dbrs);	}	// REPLACE	public function replace($table, $fieldsValues){				$insert_arr = array();		foreach($fieldsValues as $field => $value)			$insert_arr[] = $field.'=\''.$value.'\'';		$insert_str = implode(',',$insert_arr);				$sql = 'REPLACE INTO '.$table.' SET '.$insert_str;		$this->query($sql);		$id = mysql_insert_id($this->_dbrs);		return $id;	}	// QUERY	public function query($query){				$sql = $query;		$this->_saveQuery($sql);		$this->_queriesNum++;				$start = microtime(1);		$rs = mysql_query($sql, $this->_dbrs) or $this->error(mysql_error($this->_dbrs), $sql);		$this->_saveQueryTime(microtime(1) - $start);				return $rs;	}		//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)	public function getOne($query, $default_value = null){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			$cell = mysql_result($rs, 0, 0);		else			$cell = $default_value;				return $cell;	}		//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)	public function getCell($query, $row, $column, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			$cell = mysql_result($rs, $row, $column);		else			$cell = $default_value;				return $cell;	}		// GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs)){			$row = mysql_result($rs, 0, 0);		}else{			$this->insert($table, $fieldsvalues);			$row = $default_value;		}		return $row;	}		// GET COL возвращает единственный столбец (первый в наборе)	public function getCol($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			for($col = array(); $row = mysql_fetch_row($rs); $col[] = $row[0]);		else			$col = $default_value;				return $col;	}		/**	 * GET COL INDEXED	 * возвращает одномерный ассоциативный массив.	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД	 * значение массива - значение второго столбца, извлекаемого из БД	 * @param string $query	 * @param mixed $default_value	 * @return array|$default_value	 */	public function getColIndexed($query, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			for($col = array(); $row = mysql_fetch_row($rs); $col[$row[0]] = $row[1]);		else			$col = $default_value;				return $col;	}		// GET ROW возвращает единственную строку (первую в наборе)	public function getRow($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			$row = mysql_fetch_assoc($rs);		else			$row = $default_value;				return $row;	}		// GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs)){			$row = mysql_fetch_assoc($rs);		}else{			$this->insert($table, $fieldsvalues);			$row = $default_value;		}		return $row;	}		// GET ALL формирует многомерный ассоциативный массив	public function getAll($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			for($data = array(); $row = mysql_fetch_assoc($rs); $data[] = $row);		else			$data = $default_value;				return $data;	}		// GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 	public function getAllIndexed($query, $index, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && mysql_num_rows($rs))			for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);		else			$data = $default_value;		return $data;	}		// ESCAPE	public function escape($str){				if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())				$str = stripslashes($str);			$str = mysql_real_escape_string($str, $this->_dbrs);		}		return $str;	}		// ЗАКЛЮЧЕНИЕ ИМЕНИ ПОЛЯ В КАВЫЧКИ	public function quoteFieldName($fieldname){		return "`".$fieldname."`";	}		// DESCRIBE	public function describe($table){				return $this->getAll('DESCRIBE '.$table);	}		// ПОЛУЧИТЬ СПИСОК ТАБЛИЦ	public function showTables(){			return $this->getCol('SHOW TABLES');	}		// ПОКАЗАТЬ СТРОКУ CREATE TABLE	public function showCreateTable($table){			$data = $this->getCell('SHOW CREATE TABLE "'.$table.'"', 0, 1);	}		// СОЗДАТЬ ДАМП ДАННЫХ	public function makeDump(){		$lf = "\n";		$cmnt = '#';		$tables = array();		$createtable = array();				$PHP_EVAL_MODE = FALSE;		$cmnt = $PHP_EVAL_MODE ? '//' : '#';				$tables = $this->getCol('SHOW TABLES');		// get 'table create' parts for all tables		foreach ($tables as $table){			$createtable[$table] = $this->showCreateTable();		}				header('Expires: 0');		header('Cache-Control: private');		header('Pragma: cache');		header('Content-type: application/download');		header('Content-Disposition: attachment; filename='.strtolower(date("d_M_Y")).'_db_'.$this->connDatabase.'_backup.sql');				echo $cmnt." ".$lf;		echo $cmnt." START DATABASE DUMP".$lf;		echo $cmnt." dump created with Vik-Off-Dumper".$lf;		echo $cmnt." ".$lf;		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;		echo $cmnt." Database : ".$this->connDatabase.$lf;		echo $cmnt." Generation Time: ".date("d M Y H:i:s").$lf;		echo $cmnt." MySQL Server version: ".mysql_get_server_info().$lf;		echo $cmnt." PHP Version: ".phpversion().$lf;		echo $cmnt."";		foreach($tables as $table){			echo $lf;			echo $cmnt." --------------------------------------------------------".$lf;			echo $lf;			echo $cmnt."".$lf;			echo $cmnt.' TABLE '.$table.' STRUCTURE'.$lf;			echo $cmnt."".$lf;			echo $lf;						if($PHP_EVAL_MODE)				echo '$this->query("'.$lf;							echo "DROP TABLE IF EXISTS ".$table.';'.$lf;						if($PHP_EVAL_MODE)				echo '");'.$lf;							echo $lf;			if($PHP_EVAL_MODE)				echo '$this->query("'.$lf;							echo $createtable[$table].';'.$lf;						if($PHP_EVAL_MODE)				echo '");'.$lf;							echo $lf;						$numRows = $this->getOne('SELECT COUNT(*) FROM '.$table);						if($numRows){								// за раз из таблицы извлекается 100 строчек				$rowsPerIteration = 100;				$numIterations = ceil($numRows / $rowsPerIteration);								// извлечение названий полей				$fields = array();				foreach($this->getAll('DESCRIBE '.$table, array()) as $f)					$fields[] = $f['Field'];									for($i = 0; $i < $numIterations; $i++){									$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());					foreach($rows as $rowIndex => $row){						foreach($row as $field => $cell){							$cell = addslashes($cell);							$cell = str_replace("\n", '\\r\\n', $cell);							$cell = str_replace("\r", '', $cell);							$row[$field] = "'".$cell."'";						}						$rows[$rowIndex] = $lf."\t(".implode(',', $row).")";					}									echo $cmnt.$lf;					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;					echo $cmnt.$lf;					echo $lf;					if($PHP_EVAL_MODE)						echo '$this->query("'.$lf;											echo "INSERT INTO ".$table." (".implode(', ', $fields).") VALUES ".implode(',', $rows).';'.$lf;										if($PHP_EVAL_MODE)						echo '");'.$lf;											echo $lf;				}			}		}		echo $cmnt." ".$lf;		echo $cmnt." END DATABASE DUMP".$lf;		echo $cmnt." ".$lf;				exit();	}	}class DbAdapter_postgres extends DbAdapter{ 	// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ	public function connect(){				$connString = 'host='.$this->connHost.' port='.$this->connPort.' user='.$this->connUser.' password='.$this->connPass.' dbname='.$this->connDatabase;		$this->_dbrs = pg_connect($connString) or $this->error('Невозможно подключиться к серверу PgSQL');				// if(!empty($this->_encoding))			// mysql_query('SET NAMES '.$this->_params['encoding'], $this->_dbrs)or $this->error('Невозможно установить кодировку соединения с БД: '.mysql_error());			$this->_connected = TRUE;	}		// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ	public function setEncoding($encoding){				$this->_encoding = $encoding;				// if($this->isConnected())			// $this->query('SET NAMES '.$this->_encoding);	}		// ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY	public function getLastId($tablename = null, $fieldname = null){				if(is_null($tablename) || is_null($fieldname))			trigger_error('Имя таблицы и поля обязательны для заполнения', E_USER_ERROR);					return $this->getOne('SELECT last_value FROM '.$tablename.'_'.$fieldname.'_seq');	}		// ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ	public function getAffectedNum(){				return pg_affected_rows($this->_dbrs);	}	// INSERT	public function insert($table, $fieldsValues, $autoIncrementField = null){				$insert_arr = array();		foreach($fieldsValues as $field => $value)			$insert_arr[] = $field.'=\''.$value.'\'';		$insert_str = implode(',',$insert_arr);				$sql = 'INSERT INTO '.$table.' SET '.$insert_str.(!is_null($autoIncrement) ? 'RETURNING '.$autoIncrementField : '');				if(!is_null($autoIncrement)){			return $this->getOne($sql);		}else{			$this->query($sql);			return null;		}	}	// QUERY	public function query($sql){				$this->saveQuery($sql);		$rs = pg_query($this->_dbrs, $sql) or $this->error(pg_last_error($this->_dbrs), $sql);		return $rs;	}		//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)	public function getOne($query, $default_value = null){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			$cell = pg_fetch_result($rs, 0, 0);		else			$cell = $default_value;		$this->freeResult($rs);		return $cell;	}		//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)	public function getCell($query, $row, $column, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			$cell = pg_fetch_result($rs, $row, $column);		else			$cell = $default_value;				$this->freeResult($rs);		return $cell;	}		// GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs)){			$row = pg_fetch_result($rs, 0, 0);		}else{			$this->insert($table, $fieldsvalues);			$row = $default_value;		}		$this->freeResult($rs);		return $row;	}		// GET COL возвращает единственный столбец (первый в наборе)	public function getCol($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			for($col = array(); $row = pg_fetch_row($rs); $col[] = $row[0]);		else			$col = $default_value;				$this->freeResult($rs);		return $col;	}		/**	 * GET COL INDEXED	 * возвращает одномерный ассоциативный массив.	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД	 * значение массива - значение второго столбца, извлекаемого из БД	 *	 * @param string $query	 * @param string $index	 * @param mixed $default_value	 * @return array	 */	public function getColIndexed($query, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			for($col = array(); $row = pg_fetch_row($rs); $col[$row[0]] = $row[1]);		else			$col = $default_value;				$this->freeResult($rs);		return $col;	}		// GET ROW возвращает единственную строку (первую в наборе)	public function getRow($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			$row = pg_fetch_assoc($rs);		else			$row = $default_value;				$this->freeResult($rs);		return $row;	}		// GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs)){			$row = pg_fetch_assoc($rs);		}else{			$this->insert($table, $fieldsvalues);			$row = $default_value;		}		$this->freeResult($rs);		return $row;	}		// GET ALL формирует многомерный ассоциативный массив	public function getAll($query, $default_value = array()){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			for($data = array(); $row = pg_fetch_assoc($rs); $data[] = $row);		else			$data = $default_value;				$this->freeResult($rs);		return $data;	}		// GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 	public function getAllIndexed($query, $index, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs) && pg_num_rows($rs))			for($data = array(); $row = pg_fetch_assoc($rs); $data[$row[$index]] = $row);		else			$data = $default_value;		$this->freeResult($rs);		return $data;	}		// ESCAPE	public function escape($str){				if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())				$str = stripslashes($str);			$str = pg_escape_string($this->_dbrs, $str);		}		return $str;	}		public function showTables(){			return $this->getCol("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");	}		// DESCRIBE	public function describe($table){				return $this->getAll('DESCRIBE '.$table);	}		public function makeDump(){		$lf = "\n";		$cmnt = '#';		$tables = array();		$createtable = array();				$PHP_EVAL_MODE = FALSE;		$cmnt = $PHP_EVAL_MODE ? '//' : '#';				$tables = $this->getCol('SHOW TABLES');		// get 'table create' parts for all tables		foreach ($tables as $table){			$createtable[$table] = $this->getCell('SHOW CREATE TABLE '.$table, 0, 1);		}				header('Expires: 0');		header('Cache-Control: private');		header('Pragma: cache');		header('Content-type: application/download');		header('Content-Disposition: attachment; filename='.strtolower(date("d_M_Y")).'_db_'.self::$connDatabase.'_backup.sql');				echo $cmnt." ".$lf;		echo $cmnt." START DATABASE DUMP".$lf;		echo $cmnt." dump created with YDumper".$lf;		echo $cmnt." ".$lf;		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;		echo $cmnt." Database : ".self::$connDatabase.$lf;		echo $cmnt." Generation Time: ".date("d M Y H:i:s", (time() - date("Z") + 10800)).$lf;		echo $cmnt." MySQL Server version: ".mysql_get_server_info().$lf;		echo $cmnt." PHP Version: ".phpversion().$lf;		echo $cmnt."";		foreach($tables as $table){			echo $lf;			echo $cmnt." --------------------------------------------------------".$lf;			echo $lf;			echo $cmnt."".$lf;			echo $cmnt.' TABLE '.$table.' STRUCTURE'.$lf;			echo $cmnt."".$lf;			echo $lf;						if($PHP_EVAL_MODE)				echo '$this->query("'.$lf;							echo "DROP TABLE IF EXISTS ".$table.';'.$lf;						if($PHP_EVAL_MODE)				echo '");'.$lf;							echo $lf;			if($PHP_EVAL_MODE)				echo '$this->query("'.$lf;							echo $createtable[$table].';'.$lf;						if($PHP_EVAL_MODE)				echo '");'.$lf;							echo $lf;						$numRows = $this->getOne('SELECT COUNT(*) FROM '.$table);						if($numRows){								// за раз из таблицы извлекается 100 строчек				$rowsPerIteration = 100;				$numIterations = ceil($numRows / $rowsPerIteration);								// извлечение названий полей				$fields = array();				foreach($this->getAll('DESCRIBE '.$table, array()) as $f)					$fields[] = $f['Field'];									for($i = 0; $i < $numIterations; $i++){									$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());					foreach($rows as $rowIndex => $row){						foreach($row as $field => $cell){							$cell = addslashes($cell);							$cell = str_replace("\n", '\\r\\n', $cell);							$cell = str_replace("\r", '', $cell);							$row[$field] = $this->quote($cell);						}						$rows[$rowIndex] = $lf."\t(".implode(',', $row).")";					}									echo $cmnt.$lf;					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;					echo $cmnt.$lf;					echo $lf;					if($PHP_EVAL_MODE)						echo '$this->query("'.$lf;											echo "INSERT INTO ".$table." (".implode(', ', $fields).") VALUES ".implode(',', $rows).';'.$lf;										if($PHP_EVAL_MODE)						echo '");'.$lf;											echo $lf;				}			}		}		echo $cmnt." ".$lf;		echo $cmnt." END DATABASE DUMP".$lf;		echo $cmnt." ".$lf;				exit();	}	}class DbAdapter_sqlite extends DbAdapter{		// ПОДКЛЮЧИТЬСЯ К БАЗЕ ДАННЫХ	public function connect(){				$this->_dbrs = sqlite_open($this->connDatabase)or $this->error('Невозможно подключиться к базе данных');		$this->_connected = TRUE;	}	// УСТАНОВИТЬ КОДИРОВКУ СОЕДИНЕНИЯ	public function setEncoding($encoding){		}		// ПОЛУЧИТЬ ПОСЛЕДНИЙ ВСТАВЛЕННЫЙ PRIMARY KEY	public function getLastId(){			return sqlite_last_insert_rowid($this->_dbrs);	}		// ПОЛУЧИТЬ КОЛИЧЕСТВО СТРОК, ЗАТРОНУТЫХ ПОСЛЕДНЕЙ ОПЕРАЦИЕЙ	public function getAffectedNum(){				return sqlite_changes($this->_dbrs);	}	// функция QUERY	public function query($query){				$sql = $query;		$this->_saveQuery($sql);		$this->_queriesNum++;				$start = microtime(1);		$rs = sqlite_query($this->_dbrs, $sql) or $this->error(sqlite_error_string(sqlite_last_error($this->_dbrs)), $sql);		$this->_saveQueryTime(microtime(1) - $start);				return $rs;	}		//функция GET ONE выполняет запрос и возвращает единственное значение (первая строка, первый столбец)	public function getOne($query, $default_value = 0){				$rs = $this->query($query);		$data = sqlite_fetch_single($rs);		if($data !== FALSE)			return $data;		else			return $default_value;	}		//функция GET CELL выполняет запрос и возвращает единственное значение (указанные строка и столбец)	public function getCell($query, $row, $column, $default_value = 0){				// $rs = $this->query($query);		// if(mysql_num_rows($rs))			// $cell = mysql_result($rs, $row, $column);		// else			// $cell = $default_value;				// return $cell;	}		// функция GET STATIC ONE возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticOne($query, $table, $fieldsvalues, $default_value = array()){				// $rs = $this->query($query);		// if(mysql_num_rows($rs)){			// $row = mysql_result($rs, 0, 0);		// }else{			// $this->insert($table, $fieldsvalues);			// $row = $default_value;		// }		// return $row;	}		// функция GET COL возвращает единственный столбец (первый в наборе)	public function getCol($query, $default_value = array()){				$rs = $this->query($query);		for($data = array(); $row = sqlite_fetch_single($rs); $data[] = $row);		if(count($data))			return $data;		else			return $default_value;	}		/**	 * GET COL INDEXED	 * возвращает одномерный ассоциативный массив.	 * Для каждой пары ключ массива - значение первого столбца, извлекаемого из БД	 * значение массива - значение второго столбца, извлекаемого из БД	 * @param string $query	 * @param mixed $default_value	 * @return array|$default_value	 */	public function getColIndexed($query, $default_value = 0){				$rs = $this->query($query);		if(is_resource($rs))			for($col = array(); $row = sqlite_fetch_array($rs, SQLITE_NUM); $col[$row[0]] = $row[1]);		else			$col = $default_value;				return $col;	}	// функция GET ROW возвращает единственную строку (первую в наборе)	public function getRow($query, $default_value = 0){				$rs = $this->query($query);		$data = sqlite_fetch_array($rs, SQLITE_ASSOC);		if(is_array($data))			return $data;		else			return $default_value;	}		// функция GET STATIC ROW возвращает единственную строку, а если строка не найдена, то вставляет ее в таблицу	public function getStaticRow($query, $table, $fieldsvalues, $default_value = array()){				$rs = $this->query($query);		if(mysql_num_rows($rs)){			$row = mysql_fetch_assoc($rs);		}else{			$this->insert($table, $fieldsvalues);			$row = $default_value;		}		return $row;	}		// функция GET ALL формирует многомерный ассоциативный массив	public function getAll($query, $default_value = array()){		$rs = $this->query($query);		$data = sqlite_fetch_all($rs, SQLITE_ASSOC);		if(count($data))			return $data;		else			return $default_value;	}		// функция GET ALL INDEXED формирует многомерный индексированный ассоциативный массив 	public function getAllIndexed($query, $index, $default_value = 0){				// $rs = $this->query($query);		// if(mysql_num_rows($rs))			// for($data = array(); $row = mysql_fetch_assoc($rs); $data[$row[$index]] = $row);		// else			// $data = $default_value;		// return $data;	}		// ESCAPE	public function escape($str){				if(!in_array(strtolower(gettype($str)), array('integer', 'double', 'boolean', 'null'))){			if(get_magic_quotes_gpc() || get_magic_quotes_runtime())				$str = stripslashes($str);			$str = sqlite_escape_string($str);		}		return $str;	}		// QUOTE FIELD NAME	public function quoteFieldName($field){		return "'".$field."'";	}		// DESCRIBE	public function describe($table){				return $this->getAll('PRAGMA table_info('.$table.')');	}		// ПОЛУЧИТЬ СПИСОК ТАБЛИЦ	public function showTables(){			return $this->getCol('SELECT name FROM sqlite_master WHERE type = "table"');	}		// ПОКАЗАТЬ СТРОКУ CREATE TABLE	public function showCreateTable($table){			return $this->getOne('SELECT sql FROM sqlite_master WHERE type = "table" AND name= "'.$table.'"');	}		// СОЗДАТЬ ДАМП ДАННЫХ	public function makeDump(){		$lf = "\n";		$cmnt = '#';		$tables = array();		$createtable = array();				$cmnt = '--';				$tables = $this->showTables();		// get 'table create' parts for all tables		foreach ($tables as $table){			$createtable[$table] = $this->showCreateTable($table);		}				header('Expires: 0');		header('Cache-Control: private');		header('Pragma: cache');		header('Content-type: application/download');		header('Content-Disposition: attachment; filename='.strtolower(date("Y_m_d")).'_backup_'.self::$connDatabase.'.sql');				echo $cmnt." ".$lf;		echo $cmnt." START SQLITE DATABASE DUMP".$lf;		echo $cmnt." dump created with YDumper".$lf;		echo $cmnt." ".$lf;		echo $cmnt." Host: ".$_SERVER['SERVER_NAME'].$lf;		echo $cmnt." Database : ".self::$connDatabase.$lf;		echo $cmnt." Generation Time: ".date("d M Y H:i:s", (time() - date("Z") + 10800)).$lf;		echo $cmnt." PHP Version: ".phpversion().$lf;		echo $cmnt."";		foreach($tables as $table){			echo $lf;			echo $cmnt." --------------------------------------------------------".$lf;			echo $lf;			echo $cmnt."".$lf;			echo $cmnt.' TABLE '.$table.' STRUCTURE'.$lf;			echo $cmnt."".$lf;			echo $lf;							echo "DROP TABLE IF EXISTS ".$table.';'.$lf;			echo $lf;							echo $createtable[$table].';'.$lf;			echo $lf;						$numRows = $this->getOne('SELECT COUNT(1) FROM '.$table);						if($numRows){								// за раз из таблицы извлекается 100 строчек				$rowsPerIteration = 100;				$numIterations = ceil($numRows / $rowsPerIteration);									for($i = 0; $i < $numIterations; $i++){									$rows = db::get()->getAll('SELECT * FROM '.$table.' LIMIT '.($i * $rowsPerIteration).', '.$rowsPerIteration, array());									echo $cmnt.$lf;					echo $cmnt.' TABLE '.$table.' DUMP'.$lf;					echo $cmnt.$lf;					echo $lf;											foreach($rows as $rowIndex => $row){						foreach($row as $field => $cell){							$cell = addslashes($cell);							$cell = str_replace("\n", '\\r\\n', $cell);							$cell = str_replace("\r", '', $cell);							$row[$field] = "'".$cell."'";						}						echo "INSERT INTO ".$table." VALUES(".implode(',', $row).");".$lf;					}					echo $lf;				}			}		}		echo $cmnt." ".$lf;		echo $cmnt." END DATABASE DUMP".$lf;		echo $cmnt." ".$lf;				exit();	}}?>
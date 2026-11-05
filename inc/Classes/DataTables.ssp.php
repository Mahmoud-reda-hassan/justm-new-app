<?php

/*
 * دالات مساعدة لبناء استعلام SQL لمعالجة بيانات DataTables من جانب الخادم
 *
 * الدالات الثابتة في هذه الفئة مجرد دالات مساعدة لمُنشئ استعلام SQL المُستخدم في نصوص معالجة البيانات من جانب الخادم لـ DataTables. من الواضح أن هذه الدالات لا تمثل كل ما يمكن فعله باستخدام المعالجة من جانب الخادم، فهي بسيطة عمدًا لإظهار كيفية عملها. يُحتمل أن تتطلب عمليات معالجة البيانات الأكثر تعقيدًا من جانب الخادم نصًا مخصصًا.
 *
 * راجع https://datatables.net/usage/server-side للاطلاع على جميع تفاصيل متطلبات معالجة البيانات من جانب الخادم لـ DataTables.
 *
 * @رخصة MIT - https://datatables.net/license_mit
 */


// يُرجى إزالة الأسطر الأربعة التالية لأنها تُستخدم في بيئة اختبار DataTables لمحيطك المحلي أو المباشر. يرجى إزالتها وإلا فلن تعمل
$file = $_SERVER['DOCUMENT_ROOT'].'/datatables/pdo.php';
if ( is_file( $file ) ) {
	include( $file );
}

class SSP {
	
	/**
	 * إنشاء مصف إخراج البيانات لصفوف DataTables
	 *
	 * @param  array $columns مجموعة معلومات الأعمدة
	 * @param  array $data    البيانات المسترجعة من تعليمة SQL
	 * @return array          البيانات المُهيأة في تنسيق صف
	 */
	static function data_output ( $columns, $data )
	{
		$out = array();
		for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
			$row = array();
			for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
				$column = $columns[$j];
				// Is there a formatter?
				if ( isset( $column['formatter'] ) ) {
                    if(empty($column['db'])){
                        $row[ $column['dt'] ] = $column['formatter']( $data[$i] );
                    }
                    else{
                        $row[ $column['dt'] ] = $column['formatter']( $data[$i][ $column['db'] ], $data[$i] );
                    }
				}
				else {
                    if(!empty($column['db'])){
                        $row[ $column['dt'] ] = $data[$i][ $columns[$j]['db'] ];
                    }
                    else{
                        $row[ $column['dt'] ] = "";
                    }
				}
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * اتصال بقاعدة البيانات
	 *
	 * الحصول على اتصال PHP PDO من مصف تفاصيل الاتصال
	 *
	 * @param  mixed $conn تفاصيل اتصال SQL. يجب أن يحتوي المصف على الخصائص التالية
	 *     * host - اسم المضيف
	 *     * db   - اسم قاعدة البيانات
	 *     * user - اسم المستخدم
	 *     * pass - كلمة مرور المستخدم
	 *     * اختياري: `'charset' => 'utf8'` - قد تحتاج إلى هذا حسب تكوين PHP / MySQL
	 * @return PDO اتصال PDO
	 */
	static function db ( $conn )
	{
		if ( is_array( $conn ) ) {
			return self::sql_connect( $conn );
		}

		return $conn;
	}

	/**
	 * ترقيم الصفحات
	 *
	 * بناء عبارة LIMIT لاستعلام SQL لمعالجة البيانات من جانب الخادم
	 *
	 * @param  array $request البيانات المُرسلة إلى الخادم بواسطة DataTables
	 * @param  array $columns معلومات الأعمدة
	 * @return string عبارة SQL limit
	 */
	static function limit ( $request, $columns )
	{
		$limit = '';

		if ( isset($request['start']) && $request['length'] != -1 ) {
			$limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
		}

		return $limit;
	}

	/**
	 * الترتيب
	 *
	 * إنشاء جملة ORDER BY لاستعلام SQL لمعالجة البيانات من جانب الخادم
	 *
	 * @param  array $request البيانات المرسلة إلى الخادم بواسطة DataTables
	 * @param  array $columns مصفوفة معلومات الأعمدة
	 * @return string جملة ترتيب SQL
	 */
	static function order ( $request, $columns )
	{
		$order = '';

		if ( isset($request['order']) && count($request['order']) ) {
			$orderBy = array();
			$dtColumns = self::pluck( $columns, 'dt' );

			for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
				$columnIdx = $request['order'][$i]['column'];
				$requestColumn = $request['columns'][$columnIdx];
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['orderable'] == 'true' ) {
					$dir = $request['order'][$i]['dir'] === 'asc' ?
						'ASC' :
						'DESC';

					$orderBy[] = '`'.$column['db'].'` '.$dir;
				}
			}

			if ( count( $orderBy ) ) {
				$order = ' ORDER BY '.implode(', ', $orderBy).' ';
			}
		}

		return $order;
	}

	/**
	 * البحث / التصفية
	 *
	 * إنشاء بند WHERE لاستعلام SQL لمعالجة البيانات من جانب الخادم.
	 *
	 * ملاحظة: لا يتطابق هذا مع عملية التصفية المُضمنة في DataTables التي تقوم بالبحث كلمة بكلمة على أي حقل. من الممكن القيام بذلك هنا، ولكن الأداء على قواعد البيانات الكبيرة سيكون سيئًا للغاية.
	 *
	 * @param array $request البيانات المُرسلة إلى الخادم بواسطة DataTables
	 * @param array $columns مصف المعلومات حول الأعمدة
	 * @param array $bindings مصف القيم لإرتباطات PDO، المُستخدمة في دالة sql_exec()
	 * @return string شرط WHERE في SQL
	 */
	static function filter ( $request, $columns, &$bindings )
	{
		$globalSearch = array();
		$columnSearch = array();
		$dtColumns = self::pluck( $columns, 'dt' );

		if ( isset($request['search']) && $request['search']['value'] != '' ) {
			$str = $request['search']['value'];

			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['searchable'] == 'true' ) {
					if(!empty($column['db'])){
						$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
						$globalSearch[] = "`".$column['db']."` LIKE ".$binding;
					}
				}
			}
		}

		// Individual column filtering
		if ( isset( $request['columns'] ) ) {
			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				$str = $requestColumn['search']['value'];

				if ( $requestColumn['searchable'] == 'true' &&
				 $str != '' ) {
					if(!empty($column['db'])){
						$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
						$columnSearch[] = "`".$column['db']."` LIKE ".$binding;
					}
				}
			}
		}

		// Combine the filters into a single string
		$where = '';

		if ( count( $globalSearch ) ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where = $where === '' ?
				implode(' AND ', $columnSearch) :
				$where .' AND '. implode(' AND ', $columnSearch);
		}

		if ( $where !== '' ) {
			$where = 'WHERE '.$where;
		}

		return $where;
	}

	/**
	 * تنفيذ استعلامات SQL المطلوبة لطلب عملية المعالجة من جانب الخادم، باستخدام دالات المساعدة لهذه الفئة، limit()، order() و filter() من بين أخرى. يُعد المصفوف المُرجع جاهزًا للترميز في JSON استجابة لطلب SSP، أو يمكن تعديله إذا لزم الأمر قبل إعادته إلى العميل.
	 *
	 * @param array $request البيانات المرسلة إلى الخادم بواسطة DataTables
	 * @param array|PDO $conn مورد اتصال PDO أو مصفوف معلمات الاتصال
	 * @param string $table جدول SQL للاستعلام
	 * @param string $primaryKey المفتاح الأساسي للجدول
	 * @param array $columns مصفوف معلومات العمود
	 * @return array مصفوف استجابة لعملية المعالجة من جانب الخادم
	 */
	static function simple ( $request, $conn, $table, $primaryKey, $columns )
	{
		$bindings = array();
		//$db = self::db( $conn );
		$db = $conn;

		// Allow for a JSON string to be passed in
		if (isset($request['json'])) {
			$request = json_decode($request['json'], true);
		}

		// Build the SQL query string from the request
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );

		// Main query to actually get the data
		$data = self::sql_exec( $db, $bindings,
			"SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
		);

		// Data set length after filtering
		$resFilterLength = self::sql_exec( $db, $bindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
		);
		$recordsFiltered = $resFilterLength[0][0];

		// Total data set length
		$resTotalLength = self::sql_exec( $db,
			[],
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`"
		);
		$recordsTotal = $resTotalLength[0][0];

		/*
		 * Output
		 */
		return array(
			"draw"            => isset ( $request['draw'] ) ?
				intval( $request['draw'] ) :
				0,
			"recordsTotal"    => intval( $recordsTotal ),
			"recordsFiltered" => intval( $recordsFiltered ),
			"data"            => self::data_output( $columns, $data )
		);
	}

	/**
	 * الفرق بين هذه الطريقة وطريقة `simple` هو أنه يمكنك تطبيق شروط `where` إضافية
	 * على استعلامات SQL. يمكن أن تكون هذه الشروط بأحد شكلين:
	 *
	 * * 'شرط النتيجة' - يطبق هذا الشرط على مجموعة النتائج، ولكن ليس على الاستعلام
	 *   العام للمعلومات عن الترقيم - أي أنه لن يؤثر على عدد السجلات التي يمكن للمستخدم
	 *   أن يرى أنه بإمكانه الوصول إليها. يجب استخدام هذا الشرط عندما تريد تطبيق
	 *   شرط تصفية أرسله المستخدم.
	 * * 'شرط الكل' - يطبق هذا الشرط على جميع الاستعلامات التي يتم إجراؤها ويقلل عدد
	 *   السجلات التي يمكن للمستخدم الوصول إليها. يجب استخدام هذا الشرط في الظروف
	 *   التي لا تريد فيها أن يكون للمستخدم حق الوصول إلى سجلات معينة على الإطلاق
	 *   (على سبيل المثال، التقييد حسب معرف تسجيل الدخول).
	 *
	 * في كلتا الحالتين، يمكن إضافة الشرط الإضافي كسلسلة بسيطة، أو إذا كنت تستخدم
	 * قيمًا خارجية، كصف مصفوف مرتبط مع إعدادات `condition` و`bindings`.
	 * يُعدّ `condition` سلسلة تحتوي على شرط SQL WHERE، و`bindings` عبارة عن مصفوف
	 * مرتبط بأسماء القيم المرتبطة بها.
	 *
	 * @param  array $request البيانات التي يرسلها الخادم إلى DataTables
	 * @param  array|PDO $conn مورد اتصال PDO أو مصفوف معلمات الاتصال
	 * @param  string $table جدول SQL للاستعلام
	 * @param  string $primaryKey المفتاح الأساسي للجدول
	 * @param  array $columns مصفوف معلومات العمود
	 * @param  string|array $whereResult شرط WHERE لتطبيقه على مجموعة النتائج
	 * @param  string|array $whereAll شرط WHERE لتطبيقه على جميع الاستعلامات
	 * @return array          مصفوف استجابة المعالجة من جانب الخادم
	 */
	static function complex (
		$request,
		$conn,
		$table,
		$primaryKey,
		$columns,
		$whereResult=null,
		$whereAll=null
	) {
		$bindings = array();
		$whereAllBindings = array();
		//$db = self::db( $conn );
		$db = $conn;
		$whereAllSql = '';

		// Build the SQL query string from the request
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );

		// whereResult can be a simple string, or an assoc. array with a
		// condition and bindings
		if ( $whereResult ) {
			$str = $whereResult;

			if ( is_array($whereResult) ) {
				$str = $whereResult['condition'];

				if ( isset($whereResult['bindings']) ) {
					self::add_bindings($bindings, $whereResult['bindings']);
				}
			}

			$where = $where ?
				$where .' AND '.$str :
				'WHERE '.$str;
		}

		// Likewise for whereAll
		if ( $whereAll ) {
			$str = $whereAll;

			if ( is_array($whereAll) ) {
				$str = $whereAll['condition'];

				if ( isset($whereAll['bindings']) ) {
					self::add_bindings($whereAllBindings, $whereAll['bindings']);
				}
			}

			$where = $where ?
				$where .' AND '.$str :
				'WHERE '.$str;

			$whereAllSql = 'WHERE '.$str;
		}

		// Main query to actually get the data
		$data = self::sql_exec( $db, $bindings,
			"SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
		);

		// Data set length after filtering
		$resFilterLength = self::sql_exec( $db, $bindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
		);
		$recordsFiltered = $resFilterLength[0][0];

		// Total data set length
		$resTotalLength = self::sql_exec( $db, $whereAllBindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table` ".
			$whereAllSql
		);
		$recordsTotal = $resTotalLength[0][0];

		/*
		 * Output
		 */
		return array(
			"draw"            => isset ( $request['draw'] ) ?
				intval( $request['draw'] ) :
				0,
			"recordsTotal"    => intval( $recordsTotal ),
			"recordsFiltered" => intval( $recordsFiltered ),
			"data"            => self::data_output( $columns, $data )
		);
	}

	/**
	 * الاتصال بقاعدة البيانات
	 *
	 * @param  array $sql_details مصف تفاصيل اتصال خادم SQL، يحتوي على الخصائص التالية:
	 *     * host - اسم المضيف
	 *     * db   - اسم قاعدة البيانات
	 *     * user - اسم المستخدم
	 *     * pass - كلمة مرور المستخدم
	 * @return PDO معالج اتصال قاعدة البيانات
	 */
	static function sql_connect ( $sql_details )
	{
		try {
			$db = @new PDO(
				"mysql:host={$sql_details['host']};dbname={$sql_details['db']}",
				$sql_details['user'],
				$sql_details['pass'],
				array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
			);
		}
		catch (PDOException $e) {
			self::fatal(
				"An error occurred while connecting to the database. ".
				"The error reported by the server was: ".$e->getMessage()
			);
			exit(0); // redundant, but PHPStan wants it.
		}

		return $db;
	}

	/**
	 * تنفيذ استعلام SQL على قاعدة البيانات
	 *
	 * @param PDO $db مقبض قاعدة البيانات
	 * @param array $bindings صفيف من قيم ربط PDO من bind() لاستخدامها في الهروب الآمن من السلاسل. لاحظ أنه يمكن تقديم ذلك كسلسلة استعلام SQL إذا لم تكن هناك حاجة إلى ربط.
	 * @param string $sql استعلام SQL لتنفيذه.
	 * @return array نتيجة الاستعلام (جميع الصفوف)
	 */
	static function sql_exec ( $db, $bindings, $sql=null )
	{
		// Argument shifting
		if ( $sql === null ) {
			$sql = $bindings;
		}

		$stmt = $db->prepare( $sql );

		// Bind parameters
		for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
			$binding = $bindings[$i];
			$stmt->bindValue( $binding['key'], $binding['val'], $binding['type'] );
		}

		// Execute
		try {
			$stmt->execute();
		}
		catch (PDOException $e) {
			self::fatal( "An SQL error occurred: ".$e->getMessage() );
		}

		// Return all
		return $stmt->fetchAll( PDO::FETCH_BOTH );
	}

	/*
	* طرق داخلية
	*/

	/**
	* إثارة خطأ فادح.
	*
	* يقوم هذا بكتابة رسالة خطأ في سلسلة JSON التي ستراها DataTables وتعرضها للمستخدم في المتصفح.
	*
	* @param  string $msg رسالة لإرسالها إلى العميل
	*/
	static function fatal ( $msg )
	{
		echo json_encode( array( 
			"error" => $msg
		) );

		exit(0);
	}

	/**
	 * إنشاء مفتاح ربط PDO يمكن استخدامه لتفادي المتغيرات بأمان
	 * عند تنفيذ استعلام باستخدام sql_exec()
	 *
	 * @param  array &$a    مصفوفة الربط
	 * @param  mixed  $val  القيمة للربط
	 * @param  int    $type نوع حقل PDO
	 * @return string       مفتاح مربوط للاستخدام في SQL حيث سيتم استخدام هذه المعلمة.
	 */
	static function bind ( &$a, $val, $type )
	{
		$key = ':binding_'.count( $a );

		$a[] = array(
			'key' => $key,
			'val' => $val,
			'type' => $type
		);

		return $key;
	}

	static function add_bindings(&$a, $vals)
	{
		foreach($vals['bindings'] as $key => $value) {
			$bindings[] = array(
				'key' => $key,
				'val' => $value,
				'type' => PDO::PARAM_STR
			);
		}
	}

	/**
	 * استخرج خاصية معينة من كل مصفّة تشاركية داخل مصفوفة عددية،
	 * وأعد مصفوفة تحتوي على قيم الخاصية لكل عنصر.
	 *
	 * @param  array  $a    المصفوفة لاستخراج البيانات منها
	 * @param  string $prop الخاصية المراد قراءتها
	 * @return array        المصفوفة المحتوية على قيم الخاصية
	 */
	static function pluck ( $a, $prop )
	{
		$out = array();

		for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
 			if ( empty($a[$i][$prop]) && $a[$i][$prop] !== 0 ) {
				continue;
			}

			//removing the $out array index confuses the filter method in doing proper binding,
			//adding it ensures that the array data are mapped correctly
			$out[$i] = $a[$i][$prop];
		}

		return $out;
	}

	/**
	 * استرجاع سلسلة من مصفوفة أو سلسلة نصية
	 *
	 * @param  array|string $a المصفوفة المطلوب ضمها
	 * @param  string $join علامة التشديد للوصل
	 * @return string السلسلة المضمومة
	 */
	static function _flatten ( $a, $join = ' AND ' )
	{
		if ( ! $a ) {
			return '';
		}
		else if ( is_array($a) ) {
			return implode( $join, $a );
		}
		return $a;
	}
}

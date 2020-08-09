<?php
	class Connector {
		private $host;
		private $user;
		private $password;
		private $database;
		private $charset;
		private $connection;
		public function __construct($host = 'localhost', $user = 'root', $password = '', $database = 'polemico', $charset = "utf-8", $auto_connection = true){
			$this->host =  $host;
			$this->user = $user;
			$this->password = $password;
			$this->database = $database;
			$this->charset = $charset;
			if($auto_connection) $this->connect();
		}
		public function get($var) {
			return $this->{$var};
		}
		public function set($var, $value) {
			$this->{$var} = $var;
		}
		public function connect() {
			$con = new MySQLi($this->host, $this->user, $this->password, $this->database);
			if(mysqli_connect_errno()) return false;
			$con->set_charset($this->charset);
			$this->connection = $con;
			return true;
		}
		public function query($query, $args = NULL, $result = true) {
			if($this->connection != NULL) {
				$data = $this->connection->prepare($query);
				$reflect = new ReflectionClass('mysqli_stmt');
				if($this->connection->errno)
					var_dump($this->connection->error);
				if($args != NULL && !empty($args) && is_array($args)) {
					$refArgs = array();
					foreach($args as $i => $v) $refArgs[] = &$args[$i];
					$bind_param = $reflect->getMethod('bind_param');
					$bind_param->invokeArgs($data, $args);
				}
				$results = $data->execute();
				if($result) {
					$metadata = $data->result_metadata();
					$fields = $results = array();
					while($field = $metadata->fetch_field()) {
						$var = $field->name;
						$$var = NULL;
						$fields[$var] = &$$var;
					}
					$bind_result = $reflect->getMethod('bind_result');
					$bind_result->invokeArgs($data, $fields);
					while($data->fetch()) {
						$row = array();
						foreach($fields as $fieldname => $field) $row[$fieldname] = $$fieldname;
						$results[] = $row;
					}
				} else {
					$data->store_result();
					$data->affected_rows > 0;
				}
				$data->close();
				return $results;
			}
		}
		public static function newInstance() {
			return new Connector();
		}
		public function delete($table, $where = "", $args = NULL, $list = true) {
			if($this->connection != NULL) {
				$targets = $this->query("SELECT * FROM `".$table."`".$where, $args);
				$this->query("DELETE FROM `".$table."`".$where, $args, false);
				return $list ? $targets : !empty($targets);
			}
			return $list ? array() : false;
		}
		//modes: "int", "str", "strequal", "strlike", "float", "date"
        public static function getData(&$args, &$and, array $pairs) {
            $where =  "";
            foreach($pairs as $pair) {
                $toPair = count($pair);
                list($data, $var) = $pair;
                $null = $toPair >= 3 ? $pair[2] : -1;
                if($data != $null) {
                    $mode = $toPair == 4 ? $pair[3] : "int";
                    $where.=self::dataMinning($args, $and, $data, $var, $mode);
                }
            }
            return $where;
        }
        private static function in(&$args, $vars, $values, $typing = "i", $not = "") {
            if(!is_array($values))
                $values = array($values);
            $where = " (";
            $and = "";
            $total = count($values);
            $var_binds = $not." IN (".str_repeat("?,", $total-1)."?)";
            $var_typings = str_repeat($typing, $total);
            foreach($vars as $v) {
                $where.=$and.$v.$var_binds;
                $args[0].=$var_typings;
                $args = array_merge($args, $values);
                $and = " OR ";
            }
            $where.=")";
            return $where;
        }
        private static function notIn(&$args, $vars, $values, $typing = "i") {
            return self::in($args, $vars, $values, $typing, $not = " NOT");
        }
		//modes: "int", "str", "strequal", "strlike", "float", "date"
		public static function dataMinning(&$args, &$and, $data, $var, $mode = "int") {
			$where = "";
			$equal = $diff = array();
			$datas = is_array($data) ? $data : array("max" => $data); //"max" for date mode
			$vars = is_array($var) ? $var : array($var);
			$temp = array();
			foreach($vars as $var) {
				if(mb_strpos($var, "`") === false) $temp[] = "`".$var."`";
				else $temp[] = $var;
			}
			$vars = $temp;
			switch($mode) {
				case "int":
					foreach($datas as $ID) {
						if(is_float($ID)) $diff[]=intval($ID);
						else $equal[]=intval($ID);
					}
					if(!empty($diff)) {
                        $where.=$and.self::notIn($args, $vars, $diff, "i");
						$and = " AND";
					}
					if(!empty($equal)) {
						$where.=$and.self::in($args, $vars, $equal, "i");
						$and = " AND";
					}
				break;
				case "strequal":
				case "str":
					$where.=$and.self::in($args, $vars, $data, "s");
					$and = " AND";
				break;
				case "strlike":
					$where.=$and." (";
					$and = "";
					foreach($vars as $v) foreach($datas as $data) {
						$where.=$and.$v." like ?";
						$args[0].="s";
						$args[] = "%".$data."%";
						$and = " OR ";
					}
					$where.=")";
					$and = " AND";
				break;
				case "float":
					$a = array("diff" => "!=?");
					foreach($datas as $k => $i) {
						if(isset($a[$k])) $diff[] = $i;
						else $equal[] = $i;
					}
					if(!empty($diff)) {
                        $where.=$and.self::notIn($args, $vars, $diff, "d");
                        $and = " AND";
					}
					if(!empty($equal)) {
                        $where.=$and.self::in($args, $vars, $equal, "d");
                        $and = " AND";
					}
				break;
				case "date":
					$a = array("min" => ">=?", "max" => "<=?");
					foreach($datas as $k => $d) {
						if(isset($a[$k])) $diff[$k] = $d;
						else $equal[] = $d;
					}
					if(!empty($diff)) foreach($diff as $k => $d) foreach($vars as $v) {
						$where.=$and." ".$v.$a[$k];
						$args[0].="s";
						$args[] = $d."";
						$and = " AND";
					}
					if(!empty($equal)) {
						$where.=$and." (";
						$and = "";
						foreach($equal as $d) foreach($vars as $v) {
							$where.=$and.$v."=?";
							$args[0].="s";
							$args[] = $d."";
							$and = " OR ";
						}
						$where.=")";
						$and = " AND";
					}
				break;
				default: $where = NULL; break;
			}
			return $where;
		}
        // public static function ecommerce($ondate = NULL) {
        //     if(!class_exists('LockedDays'))
        //         return true;
        //     if($ondate == NULL || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ondate))
        //         $ondate = date("Y-m-d");
        //     $ondate = LockedDate::load(-1, "0,1", NULL, 1, $ondate);
        //     return empty($ondate);
        // }
	}
?>
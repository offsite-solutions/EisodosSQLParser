<?php
  
  
  namespace Eisodos\Parsers;
  
  use Eisodos\Eisodos;
  use Eisodos\Interfaces\ParserInterface;
  use Exception;
  
  /**
   * Class SQLParser for backward compatibility
   * @package Eisodos
   */
  class SQLParser implements ParserInterface {
    
    /**
     * @var callable
     */
    private $_callback;
    
    /**
     * @inheritDoc
     */
    public function openTag(): string {
      return '<%SQL%';
    }
    
    /**
     * @inheritDoc
     */
    public function closeTag(): string {
      return '%SQL%>';
    }
    
    /**
     * SQLParser constructor.
     * @param $DBConnectors_ Eisodos\DBConnectors
     */
    public function __construct($DBConnectors_) {
    }
    
    /**
     * @param $sql_
     * @param $structureParameters_
     * @param $parameterName_
     * @param string $default
     */
    private function _getSQLParam($sql_, &$structureParameters_, $parameterName_, $default = "") {
      $parameterName_ .= "=";
      if (strpos($sql_, $parameterName_) !== false) {
        $structureParameters_[substr($parameterName_, 0, -1)] =
          Eisodos::$templateEngine->replaceParamInString(
            trim(
              substr(
                $sql_,
                strpos($sql_, $parameterName_) + strlen($parameterName_),
                strpos(substr($sql_, strpos($sql_, $parameterName_) + strlen($parameterName_)), ';') - 1
              )
            )
          );
      } else {
        $structureParameters_[substr($parameterName_, 0, -1)] = $default;
      }
      
      if ($structureParameters_[substr($parameterName_, 0, -1)] == "") {
        $structureParameters_[substr($parameterName_, 0, -1)] = $default;
      }
    }
    
    /**
     * @param $structureParameters_
     * @return string
     */
    private function _runSQL($structureParameters_): string {
      $jsonKeys = array();
      
      try {
        /**
         * @var array = ['rows'=>array, 'columns'=>array]
         */
        $resultSet = call_user_func_array($this->_callback, $structureParameters_);
        $result = '';
        
        if ($resultSet === false) {
          $alert = Eisodos::$utils->replace_all(
            Eisodos::$utils->replace_all(Eisodos::$parameterHandler->getParam("LastSQLError"), "'", ""),
            "\n",
            "\\n"
          );
          if (Eisodos::$parameterHandler->eq("SQLALERT", "T")) {
            Eisodos::$templateEngine->addToResponse(
              "<script>alert('Error running query: '+'" . $alert . "');</script>"
            );
          }
          Eisodos::$render->pageDebugInfo($alert);
          $result = "<!-- error running query -->";
        } else {
          if ((integer)$structureParameters_["ROWFROM"] < 0) {
            $structureParameters_["ROWFROM"] = "1";
          }
          
          $a = 0;
          $tr = 0;
          $rowFrom = (integer)$structureParameters_["ROWFROM"];
          
          if (!(count($resultSet['rows']) < $rowFrom)) {
            $result = Eisodos::$templateEngine->getTemplate($structureParameters_["HEADNULL"], array(), false) .
              Eisodos::$templateEngine->getTemplate($structureParameters_["ROWNULL"], array(), false) .
              Eisodos::$templateEngine->getTemplate($structureParameters_["FOOTNULL"], array(), false);
          } else {
            $LColNames = $resultSet['columns'];
            if (preg_match('/[0-9]/', $structureParameters_["ROW"]) and is_numeric(
                $structureParameters_["ROW"]
              )) {
              $k = -1;
              foreach ($LColNames as $key => $value) {
                $k++;
                if ($k == (integer)$structureParameters_["ROW"]) {
                  $RowTemplate = $key;
                  break;
                }
              }
            }
            if ($rowFrom > 1) {
              array_slice($resultSet['rows'], $rowFrom - 1);
            }
            $row = $resultSet['rows'][$a];
            do {
              $a++;
              if (((integer)$structureParameters_["TABLECOLS"] > 0) and ($structureParameters_["TABLEROWBEGIN"] != "")) {
                if (($a - 1) % (integer)$structureParameters_["TABLECOLS"] == 0) {
                  $tr++;
                  Eisodos::$parameterHandler->setParam("SQLTABLEROWCOUNT", (string)$tr);
                  $result .= Eisodos::$templateEngine->getTemplate(
                    $structureParameters_["TABLEROWBEGIN"],
                    array(),
                    false
                  );
                }
              }
              Eisodos::$parameterHandler->setParam("SQLROWRELCOUNT", (string)$a);
              Eisodos::$parameterHandler->setParam(
                "SQLROWABSCOUNT",
                (string)((integer)$structureParameters_["ROWFROM"] + $a - 1)
              );
              
              // clean up json parameters
              foreach ($jsonKeys as $jsonkey) {
                Eisodos::$parameterHandler->setParam($jsonkey, "");
              }
              $jsonKeys = array();
              
              foreach ($LColNames as $colname => $colindex) {
                Eisodos::$parameterHandler->setParam("SQL" . $colname, $row[$colname]);
                if (strpos($colname, 'json__') == 0 and $row[$colname] != "") {
                  foreach (json_decode($row[$colname], true) as $jskey => $jsvalue) {
                    Eisodos::$parameterHandler->setParam("sql" . $colname . "_" . $jskey, $jsvalue);
                    $jsonKeys[] = "sql" . $colname . "_" . $jskey;
                  }
                }
              }
              try {
                if (preg_match('/[0-9]/', $structureParameters_["ROW"])
                  and is_numeric($structureParameters_["ROW"])) {
                  $result .= Eisodos::$templateEngine->getMultiTemplate(
                    explode(",", $row[$RowTemplate]),
                    array(),
                    false
                  );
                } else {
                  throw new Exception ();
                }
              } catch (Exception $e) {
                if (strpos($structureParameters_["ROW"], '@') === 0) {
                  call_user_func(
                    "__" . substr($structureParameters_["ROW"], 1),
                    $this,
                    $row
                  );
                } else {
                  $result .= Eisodos::$templateEngine->getMultiTemplate(
                    explode(",", $structureParameters_["ROW"]),
                    array(),
                    false
                  );
                }
              }
              if (count($resultSet['rows']) > $a + 1) {
                $row = false;
              } else {
                $row = $resultSet['rows'][$a + 1];
              }
              if (((integer)$structureParameters_["TABLECOLS"] > 0) and ($structureParameters_["TABLEROWEND"] != "")) {
                if ((!$row) or ($a == (integer)$structureParameters_["ROWCOUNT"]) or ($a % (integer)$structureParameters_["TABLECOLS"] == 0)) {
                  $tr++;
                  Eisodos::$parameterHandler->setParam("SQLTABLEROWCOUNT", (string)$tr);
                  $result .= Eisodos::$templateEngine->getTemplate(
                    $structureParameters_["TABLEROWEND"],
                    array(),
                    false
                  );
                }
              }
            } while (!(!$row or $a == (integer)$structureParameters_["ROWCOUNT"]));
            
            if ((integer)$structureParameters_["ROWCOUNT"] > 0) {
              Eisodos::$parameterHandler->setParam(
                "SQLNEXTPAGE",
                (string)($a + (integer)$structureParameters_["ROWFROM"])
              );
              Eisodos::$parameterHandler->setParam(
                "SQLPREVPAGE",
                (string)((integer)$structureParameters_["ROWFROM"] - (integer)$structureParameters_["ROWCOUNT"])
              );
              $modT = "";
              if (((integer)$structureParameters_["ROWFROM"] == 1) and ($row)) {
                $modT = $structureParameters_["PAGEFIRST"];
              }
              if (((integer)$structureParameters_["ROWFROM"] > 1) and ($row)) {
                $modT = $structureParameters_["PAGEINNER"];
              }
              if (((integer)$structureParameters_["ROWFROM"] > 1) and (!$row)) {
                $modT = $structureParameters_["PAGELAST"];
              }
              if ($modT != "") {
                if ($structureParameters_["HEAD"] != "") {
                  $result = Eisodos::$templateEngine->getTemplate(
                      $structureParameters_["HEAD"] . Eisodos::$utils->ODecode(
                        array($structureParameters_["NOHEADPAGE"], "T", "", "." . $modT)
                      ),
                      array(),
                      false
                    ) . $result;
                } elseif ($structureParameters_["NOHEADPAGE"] != "T") {
                  $result = Eisodos::$templateEngine->getTemplate($modT, array(), false) . $result;
                }
                if ($structureParameters_["FOOT"] != "") {
                  $result .= Eisodos::$templateEngine->getTemplate(
                    $structureParameters_["FOOT"] . Eisodos::$utils->ODecode(
                      array($structureParameters_["NOFOOTPAGE"], "T", "", "." . $modT)
                    ),
                    array(),
                    false
                  );
                } elseif ($structureParameters_["NOFOOTPAGE"] != "T") {
                  $result .= Eisodos::$templateEngine->getTemplate($modT, array(), false);
                }
              } else {
                if ($structureParameters_["HEAD"] != "") {
                  $result = Eisodos::$templateEngine->getTemplate(
                      $structureParameters_["HEAD"],
                      array(),
                      false
                    ) . $result;
                }
                if ($structureParameters_["FOOT"] != "") {
                  $result .= Eisodos::$templateEngine->getTemplate(
                    $structureParameters_["FOOT"],
                    array(),
                    false
                  );
                }
              }
            } else {
              if ($structureParameters_["HEAD"] != "") {
                $result = Eisodos::$templateEngine->getTemplate(
                    $structureParameters_["HEAD"],
                    array(),
                    false
                  ) . $result;
              }
              if ($structureParameters_["FOOT"] != "") {
                $result .= Eisodos::$templateEngine->getTemplate(
                  $structureParameters_["FOOT"],
                  array(),
                  false
                );
              }
            }
          }
        }
      } catch (Exception $e) {
        Eisodos::$logger->writeErrorLog($e);
        $result = "<!-- error running query -->";
      }
      
      return $result;
    }
    
    /**
     * @inheritDoc
     */
    public function parse($text_, $blockPosition = false): string {
      $LSQL = array();
      
      $orig = substr($text_, $blockPosition);
      $orig = substr($orig, 0, strpos($orig, '%SQL%>') + 5);
      $sql = substr($orig, 6, -6);
      
      $this->_getSQLParam($sql, $LSQL, 'DB', 'db1');
      $this->_getSQLParam($sql, $LSQL, 'CONVERTLATIN2UTF8');
      $this->_getSQLParam($sql, $LSQL, 'CONVERTUTF82LATIN');
      $this->_getSQLParam($sql, $LSQL, 'ROW');
      $this->_getSQLParam($sql, $LSQL, 'HEAD');
      $this->_getSQLParam($sql, $LSQL, 'FOOT');
      $this->_getSQLParam($sql, $LSQL, 'ROWNULL');
      $this->_getSQLParam($sql, $LSQL, 'HEADNULL');
      $this->_getSQLParam($sql, $LSQL, 'FOOTNULL');
      $this->_getSQLParam($sql, $LSQL, 'PAGEFIRST');
      $this->_getSQLParam($sql, $LSQL, 'PAGELAST');
      $this->_getSQLParam($sql, $LSQL, 'PAGEINNER');
      $this->_getSQLParam($sql, $LSQL, 'NOHEADPAGE');
      $this->_getSQLParam($sql, $LSQL, 'NOFOOTPAGE');
      $this->_getSQLParam($sql, $LSQL, 'ROWFROM', '1');
      $this->_getSQLParam($sql, $LSQL, 'ROWCOUNT', '0');
      $this->_getSQLParam($sql, $LSQL, 'TABLECOLS', '1');
      $this->_getSQLParam($sql, $LSQL, 'TABLEROWBEGIN');
      $this->_getSQLParam($sql, $LSQL, 'TABLEROWEND');
      $this->_getSQLParam($sql, $LSQL, 'GROUP');
      $this->_getSQLParam($sql, $LSQL, 'GROUPBEGIN');
      $this->_getSQLParam($sql, $LSQL, 'GROUPEND');
      if (strpos($sql, 'SQL=') === false) {
        $LSQL['SQL'] = '';
      } else {
        $LSQL['SQL'] = trim(trim(substr($sql, strpos($sql, 'SQL=') + 4)), "\n");
      }
      $LSQL['SQL'] = Eisodos::$templateEngine->parse($LSQL['SQL']);
      try {
        if ($LSQL['ROW'] == '') {
          throw new Exception('No ROW template specified');
        }
        if ($LSQL['SQL'] == '') {
          throw new Exception('No SQL template specified');
        }
        if (Eisodos::$parameterHandler->eq('ENABLEINLINESQL', 'F', 'T')) {
          $result = Eisodos::$utils->replace_all($text_, $orig, '<!-- SQL not allowed -->', false, false);
        } else {
          $result = Eisodos::$utils->replace_all($text_, $orig, $this->_runSQL($LSQL), false, false);
        }
        
        return ($result);
      } catch (Exception $e) {
        return Eisodos::$utils->replace_all(
          $text_,
          $orig,
          '<!-- Error running query: ' . $e->getMessage() . ' -->',
          false,
          false
        );
      }
    }
    
    public function enabled(): bool {
      return true;
    }
    
  }
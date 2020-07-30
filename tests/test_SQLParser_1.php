<?php /** @noinspection ALL */
  
  use Eisodos\DBConnectors;
  use Eisodos\Eisodos;
  use Eisodos\Parsers\SQLParser;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        'test_SQLParser_1'
      ]
    );
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      'trace'
    );
    
    DBConnectors::getInstance()->init([]);
    
    Eisodos::$templateEngine->registerParser(new SQLParser());
    
    print ("* Template - test1 \n");
    print (Eisodos::$templateEngine->getTemplate('test1', [], false));
    
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
      die($e->getMessage());
    }
    
    Eisodos::$logger->writeErrorLog($e);
  }
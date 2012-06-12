<?
class SapeApiException extends Exception{}

/**
* SapeApi
* 
* интерфейс для работы с xml-rpc sape.ru
* потребуется библиотека xml-rpc для php http://sourceforge.net/projects/phpxmlrpc/files/phpxmlrpc/3.0.0beta/xmlrpc-3.0.0.beta.zip/download
* 
* пример:
* require_once './3rdparty/xmlrpc-3.0.0.beta/lib/xmlrpc.inc';
* require_once 'sape_api.class.php';
* $sape_xml = new SapeApi;
* $connect = $sape_xml->set_debug(0)->connect();
* $get_user = $connect->query('sape.get_user')->cmd(); // метод без аргументов
* $get_site_pages = $connect->query('sape.get_site_pages', array(88888))->cmd(); // метод с одним аргументом
* $get_site_pages = $connect->query('sape.get_site_pages', array(88888, 111))->cmd(); // метод с двумя аргументами
*
* @author Frenk1 aka Gudd.ini
* @version 0
*/
class SapeApi{
    /**
    * Свойства с данными для соединения с сервером xml-rpc
    */
    protected $path = '/xmlrpc/';
    protected $host = 'api.sape.ru';
    protected $port = 80;

    /**
    * Свойства с данными для авторизации
    */
    protected $login = 'login';
    protected $password = 'md5_hash'; // md5

    /**
    * Уровень режима отладки
    * 0, 1, 2
    */
    private $debug = 0;

    /**
    * объект текущего соединения
    */
    private $connect = false;

    /**
    * текущие куки
    */
    private $cookies = false;

    /**
    * результат последнего запроса
    */
    private $response = false;

    /**
    * последний сохраненный запрос
    */
    private $query = false;


    /**
    * метод для подключения к серверу и поддержания соединения
    */
    function connect() {
        $this->connect = new xmlrpc_client(
            $this->path,
            $this->host,
            $this->port
            );
        $this->connect->setDebug($this->debug);

        $query = new xmlrpcmsg(
            'sape.login',
            array(
                    php_xmlrpc_encode($this->login),
                    php_xmlrpc_encode($this->password),
                    php_xmlrpc_encode(true)
                )
            );
        $this->response = $this->connect->send($query);

        try {
            if ( !$this->response->value()->scalarval() ) {
                throw new SapeApiException('Не пришел user_id от сервера');
            }

        } catch (SapeApiException $e) {
            echo $e->getMessage();
        }

        return $this;
    }

    /**
    * установка уровня дебага
    */
    function set_debug($lvl = NULL) {
        if (!is_null($lvl)) {
            $lvl = intval($lvl);
            $this->debug = $lvl;
        }

        return $this;
    }

    /**
    * получение свежих кук от сервера
    */
    function get_cookies() {
        $this->cookies = $this->response->_cookies;
    }

    /**
    * синхронизация кук при каждом запросе
    */
    function sync_cookies() {
        $this->get_cookies();

        foreach ($this->response->_cookies as $name => $value) {
            $this->connect->setCookie($name, $value['value']);
        }
    }

    /**
    * генерирование запроса к серверу sape
    */
    function query() {
        $num_args = func_num_args();
        $args = array();
        $sape_method = func_get_arg(0);

        if ($num_args > 1) {
            foreach (func_get_args() as $num => $arg) {
                if ($num < 1) continue;
                if ($num == 1) {
                    foreach ($arg as $a) {
                        $args[] = php_xmlrpc_encode($a);
                    }
                }
            }
            $this->query = new xmlrpcmsg($sape_method, $args);

        } else {
            $this->query = new xmlrpcmsg($sape_method);
        }

        return $this;
    }

    /**
    * выполнение запроса к серверу
    */
    function cmd() {
        try {
            if (!$this->query) {
                throw new SapeApiException('Нет запроса для выполнения');
            }

        } catch (SapeApiException $e) {
            echo $e->getMessage();
        }

        $this->sync_cookies();
        $this->response = $this->connect->send($this->query);

        try {
            if ($this->response->faultCode()) {
                throw new SapeApiException('Сервер sape сообщил об ошибке');
            }

        } catch (SapeApiException $e) {
            echo $e->getMessage();
        }

        return $this->response;
    }

    /**
    * получение данных, которые остались после последнего выполненного запроса
    */
    function get_response() {
        return $this->response;
    }

}


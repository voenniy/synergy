<?php
/**
* Абстрактный класс воркера
*
* Обрабатывается таски через метод __call, который подготавливает данные для задачи и выбирает метод обработчик
*/
abstract class AbstractWorker
{
    /**
    * Игнорировать ли сигнал SIGTERM
    *
    * @var bool
    */
    protected $_ignoreSIGTERM = false;
    public static $pidPath	= null;


    public static function getPidPath()
    {
        if (!self::$pidPath) {
            self::$pidPath = $_SERVER['PWD'].'/';
        }

        return self::$pidPath;
    }

    public function __construct()
    {
        if (pcntl_fork() != 0) {
            echo "Parent";
            die();
        }

        $this->log('Start!');

        if (!extension_loaded('pcntl')) {
            $this->log("Need pcntl");
        }
        //сбрасываем маску сигналов
        pcntl_sigprocmask(SIG_UNBLOCK, array(SIGTERM,SIGTSTP,SIGINT,SIGHUP));

        //закрываем стандартные потоки
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);



        //отцепляем воркер от терминала
        if (posix_setsid() == -1) {
            $this->log("Posix error " . posix_get_last_error());
            //$this->_shutdown();
            //die();
        }

        //назначаем обработчики сигналов
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, array($this, "signalHandler"));
        pcntl_signal(SIGTSTP, array($this, "signalHandler"));
        pcntl_signal(SIGINT, array($this, "signalHandler"));
        pcntl_signal(SIGHUP, array($this, "signalHandler"));


        //создаем пидфайл процесса
        $this->_createPidFile();
        $this->init();
    }

    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGHUP:
                //рестрат самого себя
                $this->log("Restart");
                $this->_restart();
                break;
            case SIGTSTP:
            case SIGINT:
            case SIGTERM:
            if ($this->_ignoreSIGTERM) {
                $this->log("Ignore kill");
            } else {
                $this->log("Shutdown by kill");
                $this->_shutdown();
            }
            break;
            }
    }

    protected function _shutdown()
    {
        $this->_deletePidFile();
        $this->log('==================================');
        exit(0);
    }

    /**
    * Вернуть путь к пидфайлу
    *
    * @return string
    */
    protected function _getPidFileName()
    {
        return self::getPidPath().'/'.pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME).'.'.posix_getpid().'.pid';
    }

    /**
    * Создать пидфайл
    */
    protected function _createPidFile()
    {
        file_put_contents($this->_getPidFileName(), posix_getpid());
    }

    /**
    * Убить пидфайл
    *
    * @return bool
    */
    protected function _deletePidFile()
    {
        return unlink($this->_getPidFileName());
    }

    /**
    * Перезапустить себя
    */
    protected function _restart()
    {
        $cmd = $_SERVER['_'].' '.$_SERVER['PWD'].'/'.pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME).' > /dev/null &';

        system($cmd);

        $this->_shutdown();
    }

    public function run()
    {
        try {
            while ($this->job()) {
                sleep(1);
            }
        } catch (Exception $e) {
            $this->log("Exception! " . $e->getMessage());
            $this->log(print_r($e->getTrace(), 1));
        } finally {
            $this->log("Daemon stop");
            $this->_shutdown();
        }


        //запустить обработчики сигналов
        pcntl_signal_dispatch();
    }

    protected function log($message)
    {
        file_put_contents('log.log', $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Футнкция в которой описывается логика
     * @return mixed
     */
    abstract protected function job();

    /**
     * Функция - конструктор для потомков
     * @return mixed
     */
    abstract protected function init();
}

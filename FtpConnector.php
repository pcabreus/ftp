<?php
/**
 * This file is part of the PositibeLabs Projects.
 *
 * (c) Pedro Carlos Abreu <pcabreus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pcabreus\Utils\Ftp;

use Psr\Log\LoggerInterface;


/**
 * Class FtpConnector
 * @package Pcabreus\Utils\Ftp
 *
 * @author Pedro Carlos Abreu <pcabreus@gmail.com>
 */
class FtpConnector
{
    const STATE_CLOSED = 0;
    const STATE_CONNECTED = 1;
    protected $connection;
    protected $state = 0;
    protected $host;
    protected $user;
    protected $pass;
    protected $port;
    protected $timeout;

    /** @var  LoggerInterface */
    protected $logger;

    public function __construct($host, $user, $pass = '', $logger = null, $port = 21, $timeout = 90)
    {
        $this->logger = $logger;
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function establishConnection()
    {
        $this->connection = @ftp_connect($this->host, $this->port, $this->timeout);
        if ($this->connection && @ftp_login($this->connection, $this->user, $this->pass)) {
            $this->state = self::STATE_CONNECTED;
            ftp_pasv($this->connection, true);
            $this->log(
                'INFO',
                sprintf("Connected to the ftp server ``%s`` for the user: ``%s``.", $this->host, $this->user)
            );
        } else {
            $this->log(
                'ERROR',
                sprintf(
                    "Unsuccessful connection to the ftp server ``%s`` for the user: ``%s``.",
                    $this->host,
                    $this->user
                )
            );
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function putAndClose(
        $host,
        $user,
        $pass = '',
        $remoteFilename,
        $filename,
        $logger = null,
        $port = 21,
        $timeout = 90
    ) {
        $ftpConnector = new FtpConnector($host, $user, $pass, $logger, $port, $timeout);
        $ftpConnector->put($remoteFilename, $filename);
    }

    public function put($remoteFilename, $filename)
    {
        $this->establishConnection();
        if ($this->state === self::STATE_CONNECTED) {
            try {

                if ($this->createDir($remoteFilename) && ftp_put(
                        $this->connection,
                        $remoteFilename,
                        $filename,
                        FTP_BINARY
                    )) {
                    $this->log('INFO', "File ``$filename`` was transferred to the ftp server ``$remoteFilename``.");
                    $this->close();

                    return true;
                } else {
                    $this->log(
                        'ERROR',
                        "File ``$filename`` can't been transferred to the ftp server ``$remoteFilename``."
                    );
                    $this->close();

                    return false;
                }
            } catch (\Exception $e) {
                $this->log('CRITICAL', "The transfer of ``$filename`` end up with an exception: ".$e->getMessage());
                $this->close();

                return false;
            }
        }

        return false;
    }

    public function createDir($remoteFilename)
    {
        $parts = explode('/', substr($remoteFilename, 1, strrpos($remoteFilename, '/') - 1));
        foreach ($parts as $part) {
            if (!@ftp_chdir($remoteFilename, $part)) {
                @ftp_mkdir($this->connection, $part);
                ftp_chdir($this->connection, $part);
            }
        }

        return true;
    }

    public function close()
    {
        ftp_close($this->connection);
        $this->state = self::STATE_CLOSED;
    }

    private function log($level, $message)
    {
        if ($this->logger) {
            $this->logger->log($level, $message);
        }
    }
}
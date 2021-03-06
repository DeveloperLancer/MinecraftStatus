<?php declare(strict_types=1);
/**
 * @author Jakub Gniecki
 * @copyright Jakub Gniecki <kubuspl@onet.eu>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace DevLancer\MinecraftStatus;


use DevLancer\MinecraftStatus\Exception\QueryException;

/**
 * Class QueryBedrock
 * @package DevLancer\MinecraftStatus
 */
class QueryBedrock extends Query
{
    /**
     * QueryBedrock constructor.
     * @inheritDoc
     */
    public function __construct(string $host, int $port = 19132, int $timeout = 3, bool $resolveSRV = true)
    {
        parent::__construct($host, $port, $timeout, $resolveSRV);
    }

    /**
     * Copied from https://github.com/xPaw/PHP-Minecraft-Query/
     *
     * @inheritDoc
     */
    protected function getStatus(string $challenge = "")
    {
        $OFFLINE_MESSAGE_DATA_ID = pack( 'c*', 0x00, 0xFF, 0xFF, 0x00, 0xFE, 0xFE, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFD, 0x12, 0x34, 0x56, 0x78 );

        $command = pack('cQ', 0x01, time());
        $command .= $OFFLINE_MESSAGE_DATA_ID;
        $command .= pack('Q', 2);
        $length  = strlen($command);

        if($length !== fwrite($this->socket, $command, $length))
            throw new QueryException( "Failed to write on socket." );

        $data = fread($this->socket, 4096);

        if($data === false)
            throw new QueryException("Failed to read from socket." );

        if($data[ 0 ] !== "\x1C")
            throw new QueryException("First byte is not ID_UNCONNECTED_PONG.");

        if(substr($data, 17, 16 ) !== $OFFLINE_MESSAGE_DATA_ID)
            throw new QueryException("Magic bytes do not match." );

        // TODO: What are the 2 bytes after the magic?
        $data = substr($data, 35);

        // TODO: If server-name contains a ';' it is not escaped, and will break this parsing
        $data = \explode(';', $data);

        if (isset($data[2]) && !preg_match('/\A[0-9]+\z/', $data[2])) {
            $index = 2;
            while (!preg_match('/\A[0-9]+\z/', $data[$index])) {
                $data[1] .= ";" . $data[$index];
                unset($data[$index]);
            }

            $data = array_values($data);
        }

        $info = [
            'game_id'          => $data[ 0 ] ?? null,
            'hostname'         => $data[ 1 ] ?? null,
            'protocol'         => $data[ 2 ] ?? null,
            'version'          => $data[ 3 ] ?? null,
            'numplayers'       => $data[ 4 ] ?? null,
            'maxplayers'       => $data[ 5 ] ?? null,
            'server_id'        => $data[ 6 ] ?? null,
            'map'              => $data[ 7 ] ?? null,
            'game_mode'        => $data[ 8 ] ?? null,
            'nintendo_limited' => $data[ 9 ] ?? null,
            'ipv4port'         => $data[ 10 ] ?? null,
            'ipv6port'         => $data[ 11 ] ?? null,
            'extra'            => $data[ 12 ] ?? null, // What is this?
        ];

        $this->info = ($this->encoding)? (array) mb_convert_encoding($info, 'UTF-8', $this->encoding) : (array) mb_convert_encoding($info, 'UTF-8');
    }
}
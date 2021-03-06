<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 16/8/30
 * Time: 下午6:22
 */

namespace Akari\system\conn;

use \PDO;
use Akari\utility\Benchmark;
use Akari\system\db\DBAgentException;

class DBConnection {

    protected $options;

    private $readConn;
    private $writeConn;

    private $_appendMsg = '';

    const BENCHMARK_KEY = "db.Query";

    public function __construct(array $options) {
        $this->options = $options;
    }

    public function connect(array $options) {
        if (!class_exists("PDO")) {
            throw new DBException("PDO Extension not installed!");
        }

        try {
            $connection = new PDO($options['dsn'], $options['username'], $options['password'], $options['options']);
        } catch (\PDOException $e) {
            throw new DBException("Connect Failed: " . $e->getMessage());
        }

        return $connection;
    }

    public function getReadConnection() {
        if (!$this->readConn) {
            $opts = $this->options;

            // 主从分离存在从机时优先选择从机
            if (array_key_exists("slaves", $opts)) {
                $opts = $this->options['slaves'][ array_rand($opts['slaves']) ];
                $this->readConn = $this->connect($opts);
            } else {
                $this->readConn = $this->getWriteConnection();   
            }
        }

        return $this->readConn;
    }

    public function getWriteConnection() {
        if (!$this->writeConn) {
            $opts = $this->options;
            $this->writeConn = $this->connect($opts);
        }

        return $this->writeConn;
    }

    /**
     * 开始一个事务
     *
     * @return bool
     */
    public function beginTransaction() {
        return $this->getWriteConnection()->beginTransaction();
    }

    /**
     * 提交当前事务
     *
     * @return bool
     */
    public function commit() {
        return $this->getWriteConnection()->commit();
    }

    /**
     * 事务回滚
     *
     * @return bool
     */
    public function rollback() {
        return $this->getWriteConnection()->rollBack();
    }

    /**
     * 是否在事务状态
     *
     * @return bool
     */
    public function inTransaction() {
        return !!$this->getWriteConnection()->inTransaction();
    }

    /**
     * <b>这是一个底层方法</b>
     * 执行SQL
     * 
     * @param string $sql
     * @param array $values
     * @param bool $returnLastInsertId 是否返回最近插入的ID
     * @return bool|int
     */
    public function query($sql, $values = [], $returnLastInsertId = FALSE) {
        $writeConn = $this->getWriteConnection();
        $st = $this->_packPrepareSQL($this->getWriteConnection(), $sql, $values);

        if ($st->execute()) {
            $result = $returnLastInsertId ? $writeConn->lastInsertId() : $st->rowCount();
            $this->_closeConn($st);

            return $result;
        }

        $this->_throwErr($st);
    }

    /**
     * <b>这是一个底层方法</b>
     * 会调用PDO的fetchAll
     * 
     * @param string $sql
     * @param array $values
     * @param int $fetchMode see \PDO::FETCH_*
     * @return array|bool
     */
    public function fetch($sql, $values = [], $fetchMode = \PDO::FETCH_ASSOC) {
        $st = $this->_packPrepareSQL($this->getReadConnection(), $sql, $values);

        if ($st->execute()) {
            $result = $st->fetchAll($fetchMode);
            $this->_closeConn($st);

            return $result;
        }

        $this->_throwErr($st);
    }

    public function fetchOne($sql, $values = [], $fetchMode = \PDO::FETCH_ASSOC) {
        $st = $this->_packPrepareSQL($this->getReadConnection(), $sql, $values);
        if ($st->execute()) {
            $result = $st->fetch($fetchMode);
            $this->_closeConn($st);

            return $result;
        }

        $this->_throwErr($st);
    }

    /**
     * <b>这是一个底层方法</b>
     * 快速查询一列中的一个值
     *
     * @param string $sql
     * @param array $values
     * @param int $columnIdx 返回查询返回的第几个值
     * @return bool|string
     * @throws DBAgentException
     */
    public function fetchValue($sql, $values = [], $columnIdx = 0) {
        $st = $this->_packPrepareSQL($this->getReadConnection(), $sql, $values);
        if ($st->execute()) {
            $result = $st->fetchColumn($columnIdx);
            $this->_closeConn($st);

            return $result;
        }

        $this->_throwErr($st);
    }

    private function _closeConn(\PDOStatement $st) {
        $st->closeCursor();
        $this->_benchmarkEnd($st->queryString);
    }

    private function _throwErr(\PDOStatement $st) {
        $errorInfo = $st->errorInfo();
        throw new DBAgentException("Query Failed. 
        [Err] " . $errorInfo[0] . " " . $errorInfo[2] . " 
        [SQL] " . $st->queryString . $this->_appendMsg);
    }

    private function _packPrepareSQL(\PDO $conn, $sql, $values) {
        $st = $conn->prepare($sql);

        foreach ($values as $key => $value) {
            $st->bindValue($key, $value);   
        }

        $this->_benchmarkBegin();

        return $st;
    }

    public function getMetaKey($key) {
        return '`' . $key . '`';
    }

    public function resetAppendMsg() {
        $this->_appendMsg = '';
    }

    public function appendMsg($msg) {
        $this->_appendMsg = $msg;
    }

    private function _benchmarkBegin() {
        Benchmark::setTimer(self::BENCHMARK_KEY);
    }

    private function _benchmarkEnd($sql) {
        Benchmark::logParams(self::BENCHMARK_KEY, [
            'time' => Benchmark::getTimerDiff(self::BENCHMARK_KEY),
            'sql' => $sql . " " . $this->_appendMsg
        ]);
    }
}

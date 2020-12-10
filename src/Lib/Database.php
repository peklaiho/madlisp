<?php
namespace MadLisp\Lib;

use PDO;
use PDOStatement;

use MadLisp\Collection;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\Vector;

class Database implements ILib
{
    public function register(Env $env): void
    {
        $env->set('db-open', new CoreFunc('db-open', 'Open a database connection.', 1, 4,
            function (string $dsn, ?string $username = null, ?string $password = null, ?Hash $options = null) {
                return new PDO($dsn, $username, $password, $options ? $options->getData() : []);
            }
        ));

        $env->set('db-execute', new CoreFunc('db-execute', 'Execute a database statement.', 2, 3,
            function (PDO $pdo, string $sql, ?Collection $args = null) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($args ? $args->getData() : []);
                return $stmt->rowCount();
            }
        ));

        $env->set('db-query', new CoreFunc('db-query', 'Execute a database query.', 2, 4,
            function (PDO $pdo, string $sql, ?Collection $args = null, bool $rowVectors = false) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($args ? $args->getData() : []);
                $rows = $stmt->fetchAll($rowVectors ? PDO::FETCH_NUM : PDO::FETCH_ASSOC);
                $data = [];
                foreach ($rows as $row) {
                    $data[] = $rowVectors ? new Vector($row) : new Hash($row);
                }
                return new Vector($data);
            }
        ));

        $env->set('db-last-id', new CoreFunc('db-last-id', 'Get the last id of auto-increment column.', 1, 1,
            fn (PDO $pdo) => $pdo->lastInsertId()
        ));

        $env->set('db-trans', new CoreFunc('db-trans', 'Start a transaction.', 1, 1,
            fn (PDO $pdo) => $pdo->beginTransaction()
        ));

        $env->set('db-commit', new CoreFunc('db-commit', 'Commit a transaction.', 1, 1,
            fn (PDO $pdo) => $pdo->commit()
        ));

        $env->set('db-rollback', new CoreFunc('db-rollback', 'Roll back a transaction.', 1, 1,
            fn (PDO $pdo) => $pdo->rollBack()
        ));
    }
}

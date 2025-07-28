<?php

class Database
{
    /**
     * @var IDbConnection
     */
    public $Connection = null;

    public function __construct(IDbConnection $dbConnection)
    {
        $this->Connection = $dbConnection;
    }

    /**
     * Queries the database and returns an IReader
     *
     * @param ISqlCommand $command
     * @return IReader to iterate over
     */
    public function Query(ISqlCommand $command)
    {
        $this->Connection->Connect();

        //Log::Debug('Database::Query %s', $command->GetQuery());

        $reader = $this->Connection->Query($command);
        $this->Connection->Disconnect();

        return $reader;
    }

    /**
     * @param ISqlCommand $command
     * @param int $limit
     * @param int $offset
     * @return IReader to iterate over
     */
    public function LimitQuery(ISqlCommand $command, $limit, $offset = null)
    {
        $this->Connection->Connect();

        //Log::Debug('Database::LimitQuery %s', $command->GetQuery());

        $reader = $this->Connection->LimitQuery($command, $limit, $offset);
        $this->Connection->Disconnect();

        return $reader;
    }

    /**
     * Executes an alter query against the database
     *
     * @param SqlCommand $command
     * @return void
     */
    public function Execute(ISqlCommand $command)
    {
        $this->Connection->Connect();

        //Log::Debug('Database::Execute %s', $command->GetQuery());

        $this->Connection->Execute($command);
        $this->Connection->Disconnect();
    }

    /**
     * Executes an insert query against the database and returns the auto-increment id
     *
     * @param ISqlCommand $command
     * @return int last id inserted for this connection
     */
    public function ExecuteInsert(ISqlCommand $command)
    {
        $this->Connection->Connect();

        //Log::Debug('Database::ExecuteInsert %s', $command->GetQuery());

        $this->Connection->Execute($command);
        $insertedId = $this->Connection->GetLastInsertId();
        $this->Connection->Disconnect();

        return $insertedId;
    }
}

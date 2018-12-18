<?php


namespace ProcessManager\db;


interface DBManagerInterface
{
    const SAVE_LAST_N_ERRORS = 20;

    public function newConnect();

    /**
     * update process state
     *
     * @param int   $id
     * @param array $fields
     *
     * @return mixed
     */
    public function updProcessStateById($id, $fields);

    /**
     * get all process state by id, or some field
     *
     * @param int                 $id
     * @param null|string|mixed[] $field
     *
     * @return mixed
     */
    public function getProcessStateById($id, $field = null);

    /**
     * insert error to error log list
     *
     * @param int $id
     * @param string $error
     *
     * @return void
     */
    public function addErrorToList($id, string $error);
}
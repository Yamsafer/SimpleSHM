<?php

namespace Simple\SHM;

class Block
{
    /**
     * Holds the system id for the shared memory block
     *
     * @var int
     * @access protected
     */
    protected $id;

    /**
     * Holds the shared memory block id returned by shmop_open
     *
     * @var int
     * @access protected
     */
    protected $shmid;

    /**
     * Holds the default permission (octal) that will be used in created memory blocks
     *
     * @var int
     * @access protected
     */
    protected $perms = 0644;

    /**
     * Holds lock handler
     *
     * @var int
     * @access protected
     */

    protected $lockHandler = null;

    /**
     * Shared memory block instantiation
     *
     * In the constructor we'll check if the block we're going to manipulate
     * already exists or needs to be created. If it exists, let's open it.
     *
     * @access public
     * @param string $id (optional) ID of the shared memory block you want to manipulate
     */
    public function __construct($id = null)
    {
        if ($id === null) {
            $this->id = $this->generateID();
            $count = 0;
            while($this->exists($this->id) && $count++ < 100) {
                $this->id = $this->generateID();
            }
        } else {
            $this->id = $id;
        }
    }

    /**
     * Generates a random ID for a shared memory block
     *
     * @access protected
     * @return int System V IPC key generated from pathname and a project identifier
     */
    protected function generateID()
    {
        return mt_rand(100, 10000000);
    }

    /**
     * Checks if a shared memory block with the provided id exists or not
     *
     * In order to check for shared memory existance, we have to open it with
     * reading access. If it doesn't exist, warnings will be cast, therefore we
     * suppress those with the @ operator.
     *
     * @access public
     * @param string $id ID of the shared memory block you want to check
     * @return boolean True if the block exists, false if it doesn't
     */
    public function exists($id)
    {
        $status = @shmop_open($id, "a", 0, 0);
        return $status;
    }

    /**
     * Writes on a shared memory block
     *
     * First we check for the block existance, and if it doesn't, we'll create it. Now, if the
     * block already exists, we need to delete it and create it again with a new byte allocation that
     * matches the size of the data that we want to write there. We mark for deletion,  close the semaphore
     * and create it again.
     *
     * @access public
     * @param string $data The data that you wan't to write into the shared memory block
     */
    public function write($data)
    {
        if ( ! is_string($data)) $data = json_encode($data);

        $size = mb_strlen($data, 'UTF-8');

        if($this->exists($this->id)) {
            $this->shmid = shmop_open($this->id, "w", 0, 0);
            shmop_delete($this->shmid);
            shmop_close($this->shmid);
            $this->shmid = shmop_open($this->id, "c", $this->perms, $size);
            shmop_write($this->shmid, $data, 0);
        } else {
            $this->shmid = shmop_open($this->id, "c", $this->perms, $size);
            shmop_write($this->shmid, $data, 0);
        }
    }

    /**
     * Reads from a shared memory block
     *
     * @access public
     * @return string The data read from the shared memory block
     */
    public function read()
    {
        if ($this->exists($this->id)) {
            $this->shmid = shmop_open($this->id, "w", 0, 0);
        }

        if ($this->shmid) {
            $size = shmop_size($this->shmid);
            $data = shmop_read($this->shmid, 0, $size);
        } else {
            $data = null;
        }

        $result = json_decode($data, true);

        if (json_last_error() == JSON_ERROR_NONE) return $result;
        else return $data;
    }

    /**
     * Mark a shared memory block for deletion
     *
     * @access public
     */
    public function delete()
    {
        if ($this->exists($this->id)) {
            $this->shmid = shmop_open($this->id, "w", 0, 0);
            shmop_delete($this->shmid);
        }
    }

    /**
     * Gets the current shared memory block id
     *
     * @access public
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the current shared memory block permissions
     *
     * @access public
     */
    public function getPermissions()
    {
        return $this->perms;
    }

    /**
     * Sets the default permission (octal) that will be used in created memory blocks
     *
     * @access public
     * @param string $perms Permissions, in octal form
     */
    public function setPermissions($perms)
    {
        $this->perms = $perms;
    }

    /**
     * access locking function
     *
     * @return resource lock handler
     */
    public function lock()
    {
        if (function_exists('sem_get')) {
            $this->lockHandler = PHP_VERSION < 4.3 ? sem_get($this->id, 1, 0600) : sem_get($this->id, 1, 0600, 1);
            if ( ! sem_acquire($this->lockHandler)) throw new \Exception('sem_acquire failed', 400);
        } else {
            $this->lockHandler = fopen('/tmp/sm_'.md5($this->id), 'w');
            flock($this->lockHandler, LOCK_EX);
        }

        return $this->lockHandler;
    }

    /**
     * access unlocking function
     *
     * @param resource $fp lock handler
     *
     */
    public function unlock()
    {
        if (function_exists('sem_get')) {
            if ( ! sem_release($this->lockHandler)) throw new \Exception('sem_release failed', 400);
        } else {
            fclose($this->lockHandler);
        }
    }

    /**
     * Closes the shared memory block and stops manipulation
     *
     * @access public
     */
    public function __destruct()
    {
        if ($this->shmid) shmop_close($this->shmid);
    }
}

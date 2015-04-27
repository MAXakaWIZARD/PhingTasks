<?php
/**
 * Part of the Phing tasks collection by Ryan Chouinard.
 *
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2010 Ryan Chouinard
 * @license New BSD License
 */

/**
 * Creates the scaffolding necessary to perform a transformation on a set
 * of files and save them into a target directory.
 *
 * Subclasses need only implement the _process() method to define what
 * actions should occur.
 */
abstract class ProcessFilesetTask extends Task
{
    /**
     * @var PhingFile
     */
    protected $_targetDir;

    /**
     * @var array
     */
    protected $_fileSets;

    /**
     *
     */
    public function __construct()
    {
        $this->_fileSets = array();
    }

    /**
     * @return void
     */
    public function main()
    {
        $this->_checkTargetDir();

        /* @var $fileSet FileSet */
        foreach ($this->_fileSets as $fileSet) {
            $files = $fileSet->getDirectoryScanner($this->project)
                ->getIncludedFiles();

            foreach ($files as $file) {
                $targetDir = new PhingFile($this->_targetDir, dirname($file));
                if (!$targetDir->exists()) {
                    $targetDir->mkdirs();
                }
                unset ($targetDir);

                $source = new PhingFile($fileSet->getDir($this->project), $file);
                $target = $this->_calculateTarget($file);

                $this->log("Processing ${file}");

                try {
                    $successful = $this->_process($source, $target);
                } catch (Exception $e) {
                    $this->log($e->getMessage(), Project::MSG_DEBUG);
                    $successful = false;
                }

                if (!$successful) {
                    $this->log("Failed processing ${file}!", Project::MSG_ERR);
                }
            }
        }
    }

    /**
     * Calculate the target file path, based on $file and the target directory.
     * @param string $file The source file name.
     * @return PhingFile
     */
    protected function _calculateTarget($file)
    {
        return new PhingFile(
            $this->_targetDir,
            $file
        );
    }


    /**
     * Perform a transformtion on $source, saving the result into $target
     * @param PhingFile $source
     * @param PhingFile $target
     */
    abstract protected function _process($source, $target);

    /**
     * @return FileSet
     */
    public function createFileSet()
    {
        $num = array_push($this->_fileSets, new FileSet);
        return $this->_fileSets[$num - 1];
    }

    /**
     * @param PhingFile $path
     * @return void
     */
    public function setTargetDir(PhingFile $path)
    {
        $this->_targetDir = $path;
    }

    /**
     * @throws BuildException
     * @return void
     */
    protected function _checkTargetDir()
    {
        if ($this->_targetDir === null) {
            throw new BuildException(
                'Target directory must be specified',
                $this->location
            );
        }
    }
}

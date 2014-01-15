<?php
/**
 * Part of the Phing tasks collection by Ryan Chouinard.
 *
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2010 Ryan Chouinard
 * @license New BSD License
 */

require_once dirname(__FILE__) . '/ProcessFilesetTask.php';

/**
 * Defines a Phing task to run the YUI compressor against a set of JavaScript
 * or CSS files.
 *
 * A java binary must be available in the environment PATH for this task to
 * work.
 *
 * To use this task, include it with a taskdef tag in your build.xml file:
 *
 *     <taskdef name="yuic" classname="my.tasks.YuiCompressorTask" />
 *
 * The task is now ready to be used:
 *
 *     <target name="yui-compressor" description="Compress CSS and JavaScript">
 *         <yuic targetdir="path/to/target">
 *             <fileset dir="path/to/source">
 *                 <include name="*.css" />
 *                 <include name="*.js" />
 *             </fileset>
 *         </yuic>
 *     </target>
 *
 * This task makes use of the
 * {@link http://developer.yahoo.com/yui/compressor/ YUI compressor}. Version
 * 2.4.2 of the compiled jar file is bundled with this task, however a different
 * jar file may be specified using the optional jarpath attribute.
 */
class YuiCompressorTask extends ProcessFilesetTask
{
    /**
     * @var string
     */
    protected $_javaPath;

    /**
     * @var PhingFile
     */
    protected $_jarPath;

    /**
     *
     */
    public function __construct()
    {
        $defaultJarPath = realpath(
            dirname(__FILE__) . '/includes/yuicompressor-2.4.2.jar'
        );

        $this->_javaPath = 'java';
        $this->_jarPath = new PhingFile($defaultJarPath);
        parent::__construct();
    }

    /**
     * @return void
     */
    public function main()
    {
        $this->_checkJarPath();
        parent::main();
    }

    /**
     * Uses the YuiCompressor to compress $source and save the result into
     * $target
     *
     * @param PhingFile $source
     * @param PhingFile $target
     * @return bool Whether or not processing the file was successful.
     */
    protected function _process($source, $target)
    {
        $cmd = escapeshellcmd($this->_javaPath)
            . ' -jar ' . escapeshellarg($this->_jarPath)
            . ' -o ' . escapeshellarg($target->getAbsolutePath())
            . ' ' . escapeshellarg($source->getAbsolutePath());
        $this->log('Executing: ' . $cmd, Project::MSG_DEBUG);
        @exec($cmd, $output, $return);

        return $return === 0;
    }

    /**
     * @param PhingFile $path
     * @return void
     */
    public function setJarPath(PhingFile $path)
    {
        $this->_jarPath = $path;
    }

    /**
     * @throws BuildException
     */
    protected function _checkJarPath()
    {
        if ($this->_jarPath === null) {
            throw new BuildException(
                'Path to YUI compressor jar file must be specified',
                $this->location
            );
        } elseif (!$this->_jarPath->exists()) {
            throw new BuildException(
                'Unable to locate jar file at specified path',
                $this->location
            );
        }
    }
}

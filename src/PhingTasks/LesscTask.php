<?php
/**
 * Part of the Phing tasks collection by Ryan Chouinard.
 *
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2010 Ryan Chouinard
 * @license New BSD License
 */

require_once dirname(__FILE__) . '/../../vendor/autoload.php';

/**
 * Defines a Phing task to compile {@link http://lesscss.org LESS} syntax to
 * valid CSS.
 *
 * To use this task, include it with a taskdef tag in your build.xml file:
 *
 *     <taskdef name="lessc" classname="my.tasks.LesscTask" />
 *
 * The task is now ready to be used:
 *
 *     <target name="compile-less" description="Compile LESS to CSS">
 *         <lessc targetdir="path/to/published/css">
 *             <fileset dir="path/to/less/sources">
 *                 <include name="*.less" />
 *             </fileset>
 *         </lessc>
 *     </target>
 *
 * This task differs from LessCompileTask in that it uses the Node.js compiler
 * that ships with the current stable less instead of a PHP port. lessc must 
 * be on your system path for it to work.
 * @link https://github.com/cloudhead/less.js
 */
class LesscTask extends ProcessFilesetTask
{
    protected $_executable = 'lessc';

    /**
     * Set the path to the lessc executable.
     * @param string $path The path to the lessc executable.
     */
    public function setExecutable($path)
    {
        $this->_executable = $path;
    }

    /**
     * Check for the existance of lessc and then run it against the files.
     * @reutrn void
     */
    public function main()
    {
        exec($this->_executable, $output);
        if (!preg_match('/lessc:/', implode('', $output))) {
            throw new BuildException('lessc not found');
        }

        parent::main();
    }

    /**
     * Replaces the .less extension with .css
     *
     * @param string $file
     * @return PhingFile
     */
    protected function _calculateTarget($file)
    {
        return new PhingFile(
            $this->_targetDir,
            str_replace('.less', '.css', $file)
        );
    }

    /**
     * @param PhingFile $source
     * @param PhingFile $target
     * @return bool
     */
    protected function _process($source, $target)
    {
        $cmd = escapeshellcmd($this->_executable)
            . ' ' . escapeshellarg($source->getAbsolutePath())
            . ' > ' . escapeshellarg($target->getAbsolutePath());
        $this->log('Executing: ' . $cmd, Project::MSG_DEBUG);
        @exec($cmd, $output, $return);

        return true;
    }
}

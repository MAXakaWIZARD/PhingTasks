<?php
/**
 * Part of the Phing tasks collection by Ryan Chouinard.
 *
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2011 Ryan Chouinard
 * @license New BSD License
 */

/**
 * Defines a Phing task to create or verify a manifest file. This file simply
 * contains a listing of files in the specified FileSet along with a hash sum
 * value. This file can be used at a later time to verify the integrity of the
 * files.
 *
 * To use this task, include it with a taskdef tag in your build.xml file:
 *
 *     <taskdef name="manifestfile" classname="my.tasks.ManifestFileTask" />
 *
 * The task is now ready to be used:
 *
 *     <target name="create-manifest" description="Generate a Manifest file">
 *         <manifestfile file="Manifest">
 *             <fileset dir="path/to/source">
 *                 <include name="*.php" />
 *             </fileset>
 *         </manifestfile>
 *     </target>
 *
 *     <target name="verify-manifest" description="Verify the Manifest">
 *         <manifestfile file="Manifest" mode="verify">
 *             <fileset dir="path/to/source">
 *                 <include name="*.php" />
 *             </fileset>
 *         </manifestfile>
 *     </target>
 *
 * It is possible to specify the hashing algorithm to use by passing the name
 * through the algo attribute. Any algorithm supported by the PHP hash extension
 * may be used. By default, this task uses SHA256.
 */
class ManifestFileTask extends Task
{
    const MODE_CREATE = 'create';
    const MODE_VERIFY = 'verify';

    /**
     * @var string
     */
    protected $_algo;

    /**
     * @var PhingFile
     */
    protected $_file;

    /**
     * @var array
     */
    protected $_fileSets;

    /**
     * @var array
     */
    protected $_hashes;

    /**
     * @var string
     */
    protected $_mode;

    /**
     * @var boolean
     */
    protected $_verify;

    /**
     *
     */
    public function __construct()
    {
        $this->_algo = 'sha256';
        $this->_fileSets = array();
        $this->_hashes = array();
        $this->_mode = self::MODE_CREATE;
        $this->_verify = false;
    }

    /**
     * @return boolean
     */
    public function init()
    {
        return true;
    }

    /**
     * @throws BuildException
     * @return void
     */
    public function main()
    {
        $this->_checkHashExtension();
        $this->_checkAlgo();
        $this->_checkFile();

        $this->_buildHashMap();

        if (strtolower($this->_mode) == self::MODE_VERIFY) {
            $this->_verifyManifest();
        } else {
            $this->_writeManifest();
        }
    }

    /**
     * @throws BuildException
     */
    protected function _buildHashMap()
    {
        foreach ($this->_fileSets as $fileSet) {
            $this->_buildFileSetHashMap($fileSet);
        }

        ksort($this->_hashes);
    }

    /**
     * @param FileSet $fileSet
     *
     * @throws BuildException
     */
    protected function _buildFileSetHashMap($fileSet)
    {
        $files = $fileSet->getDirectoryScanner($this->project)
            ->getIncludedFiles();
        $fileSetDir = $fileSet->getDir($this->project);

        foreach ($files as $file) {
            $file = new PhingFile($fileSetDir, $file);
            $this->_buildFileHashMap($file);
        }
    }

    /**
     * @param PhingFile $file
     */
    protected function _buildFileHashMap($file)
    {
        $projBase = $this->project->getBasedir();

        $path = realpath($file->getAbsolutePath());
        $hash = hash_file($this->_algo, $path);

        if (substr($path, 0, strlen($projBase)) == $projBase) {
            $path = ltrim(
                substr($path, strlen($projBase)),
                DIRECTORY_SEPARATOR
            );
        }

        $this->_hashes[$path] = $hash;
    }

    /**
     * @throws BuildException
     * @return void
     */
    protected function _verifyManifest()
    {
        $this->_checkFileReadability();

        $manifest = $this->_readManifest();

        $verified = $this->_verifyCheckLeftSide($manifest)
            && $this->_verifyCheckRightSide($manifest)
            && $this->_verifyPresentHashes($manifest);

        if (!$verified) {
            throw new BuildException(
                'Manifest verification failed'
            );
        }

        $this->log('Manifest verification successful');
    }

    /**
     * Check for files present which are not in the manifest
     * @param $manifest
     * @return boolean
     */
    protected function _verifyCheckLeftSide($manifest)
    {
        return $this->_compareLists(
            $manifest,
            $this->_hashes,
            'There are %d files present which are not listed in the manifest',
            'Extra file'
        );
    }

    /**
     * Check for files listed in the manifest which are not present
     * @param $manifest
     * @return boolean
     */
    protected function _verifyCheckRightSide($manifest)
    {
        return $this->_compareLists(
            $this->_hashes,
            $manifest,
            'There are %d files listed in the manifest which are not present',
            'Missing file'
        );
    }

    /**
     * @param $firstList
     * @param $secondList
     * @param $nonEqualMessage
     * @param $listPrefix
     *
     * @return bool True if lists are equal
     */
    protected function _compareLists($firstList, $secondList, $nonEqualMessage, $listPrefix)
    {
        $diff = array_keys(array_diff_key($firstList, $secondList));
        $diffCount = count($diff);
        $equal = $diffCount == 0;
        if (!$equal) {
            $this->log(
                sprintf($nonEqualMessage, $diffCount),
                PROJECT::MSG_WARN
            );
            $this->_logFilesList($diff, $listPrefix);
        }

        return $equal;
    }

    /**
     * Compare manifest hashes with the computed hashes
     * @param $manifest
     * @return boolean
     */
    protected function _verifyPresentHashes($manifest)
    {
        $verified = true;

        $filesPresent = array_keys(array_intersect_key($manifest, $this->_hashes));
        foreach ($filesPresent as $path) {
            if ($manifest[$path] != $this->_hashes[$path]) {
                $verified = false;
                $this->log(
                    'Hashes do not match: ' . $path,
                    PROJECT::MSG_WARN
                );
            }
        }

        return $verified;
    }

    /**
     * @param $files
     * @param $messagePrefix
     */
    protected function _logFilesList($files, $messagePrefix)
    {
        foreach ($files as $path) {
            $this->log(
                $messagePrefix . ': ' . $path,
                PROJECT::MSG_WARN
            );
        }
    }

    /**
     * @return array
     */
    protected function _readManifest()
    {
        $manifest = array();
        $fp = fopen($this->_file, 'r');
        while ($line = trim(fgets($fp))) {
            list ($path, $hash) = explode("\t", $line);
            $manifest[$path] = $hash;
        }
        fclose($fp);

        return $manifest;
    }

    /**
     * @throws BuildException
     * @return void
     */
    protected function _writeManifest()
    {
        $manifest = '';
        foreach ($this->_hashes as $path => $hash) {
            $manifest .= "${path}\t${hash}\n";
        }

        if (file_put_contents($this->_file, $manifest, LOCK_EX) === false) {
            throw new BuildException(
                'Failed writing to manifest file',
                $this->location
            );
        }

        $this->log('Wrote ' . filesize($this->_file) . ' bytes to ' . $this->_file);
    }

    /**
     * @return FileSet
     */
    public function createFileSet()
    {
        $num = array_push($this->_fileSets, new FileSet);
        return $this->_fileSets[$num - 1];
    }

    /**
     * @param string $algo
     * @return void
     */
    public function setAlgo($algo)
    {
        $this->_algo = $algo;
    }

    /**
     * @param PhingFile $manifestFile
     * @return void
     */
    public function setFile(PhingFile $manifestFile)
    {
        $this->_file = $manifestFile;
    }

    /**
     * @param string $mode
     * @return void
     */
    public function setMode($mode)
    {
        $this->_mode = $mode;
    }

    /**
     * @throws BuildException
     * @return void
     */
    protected function _checkAlgo()
    {
        $this->_algo = strtolower($this->_algo);

        if (!in_array($this->_algo, hash_algos())) {
            throw new BuildException(
                'An invalid hashing algorithm has been specified',
                $this->location
            );
        }
    }

    /**
     * @throws BuildException
     * @return void
     */
    protected function _checkFile()
    {
        if ($this->_file === null) {
            throw new BuildException(
                'Path to manifest file must be specified',
                $this->location
            );
        }
    }

    /**
     * @throws BuildException
     */
    protected function _checkFileReadability()
    {
        if (!$this->_file->isFile() || !$this->_file->canRead()) {
            throw new BuildException(
                'Failed reading from manifest file',
                $this->location
            );
        }
    }

    /**
     * @throws BuildException
     */
    protected function _checkHashExtension()
    {
        if (!extension_loaded('hash')) {
            throw new BuildException(
                'The hash extension must be loaded to use this task',
                $this->location
            );
        }
    }
}

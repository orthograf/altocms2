<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

class ModuleUploader extends Module {

    const COOKIE_TARGET_TMP         = 'uploader_target_tmp';

    const ERR_NOT_POST_UPLOADED         = 10001;
    const ERR_NOT_FILE_VARIABLE         = 10002;
    const ERR_MAKE_UPLOAD_DIR           = 10003;
    const ERR_MOVE_UPLOAD_FILE          = 10004;
    const ERR_COPY_UPLOAD_FILE          = 10005;
    const ERR_REMOTE_FILE_OPEN          = 10011;
    const ERR_REMOTE_FILE_MAXSIZE       = 10012;
    const ERR_REMOTE_FILE_READ          = 10013;
    const ERR_REMOTE_FILE_WRITE         = 10014;
    const ERR_NOT_ALLOWED_EXTENSION     = 10051;
    const ERR_FILE_TOO_LARGE            = 10052;
    const ERR_IMG_NO_INFO               = 10061;
    const ERR_IMG_LARGE_WIDTH           = 10062;
    const ERR_IMG_LARGE_HEIGHT          = 10063;
    const ERR_IMG_NOT_ALLOWED_FORMAT    = 10081;
    const ERR_TRANSFORM_IMAGE           = 10101;

    protected $aUploadErrors
        = array(
            UPLOAD_ERR_OK                   => 'Ok',
            UPLOAD_ERR_INI_SIZE             => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE            => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL              => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE              => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR           => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE           => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION            => 'A PHP extension stopped the file upload',
            self::ERR_NOT_POST_UPLOADED     => 'File did not upload via POST method',
            self::ERR_NOT_FILE_VARIABLE     => 'Argument is not $_FILE[] variable',
            self::ERR_MAKE_UPLOAD_DIR       => 'Cannot make upload dir',
            self::ERR_MOVE_UPLOAD_FILE      => 'Cannot move uploaded file',
            self::ERR_COPY_UPLOAD_FILE      => 'Cannot copy uploaded file',
            self::ERR_REMOTE_FILE_OPEN      => 'Cannot open remote file',
            self::ERR_REMOTE_FILE_MAXSIZE   => 'Remote file is too large',
            self::ERR_REMOTE_FILE_READ      => 'Cannot read remote file',
            self::ERR_REMOTE_FILE_WRITE     => 'Cannot write remote file in tmp dir',
            self::ERR_NOT_ALLOWED_EXTENSION => 'Not allowed file extension',
            self::ERR_FILE_TOO_LARGE        => 'File is too large',
            self::ERR_IMG_NO_INFO           => 'Cannot get info about image (may be file is corrupted)',
            self::ERR_IMG_LARGE_WIDTH       => 'Width of image is too large',
            self::ERR_IMG_LARGE_HEIGHT      => 'Height of image is too large',
            self::ERR_IMG_NOT_ALLOWED_FORMAT => 'Not allowed image format',
            self::ERR_TRANSFORM_IMAGE       => 'Error during transform image',
        );

    protected $nLastError = 0;
    protected $sLastError = '';
    protected $aModConfig = [];
    protected $sDefaultDriver = 'file';
    protected $aRegisteredDrivers = [];
    protected $aLoadedDrivers = [];

    /**
     * Init module
     */
    public function init() {

        $this->aModConfig = Config::getData('module.uploader');
        $this->RegisterDriver('file');
    }

    protected function _resetError() {

        $this->nLastError = 0;
        $this->sLastError = '';
    }

    /**
     * @param string $sDriverName
     * @param string $sClass
     */
    public function registerDriver($sDriverName, $sClass = null) {

        if (!$sClass) {
            $sClass = 'Uploader_Driver' . ucfirst($sDriverName);
        }
        $this->aRegisteredDrivers[$sDriverName] = $sClass;
    }

    /**
     * @param $sDriverName
     *
     * @return Entity
     */
    public function LoadDriver($sDriverName) {

        $sClass = $this->aRegisteredDrivers[$sDriverName];
        return E::getEntity($sClass);
    }

    /**
     * @return array
     */
    public function getRegisteredDrivers() {

        return array_keys($this->aRegisteredDrivers);
    }

    /**
     * @param string $sDriverName
     */
    public function setDefaultDriver($sDriverName) {

        $this->sDefaultDriver = $sDriverName;
    }

    /**
     * @return string
     */
    public function getDefaultDriver() {

        return $this->sDefaultDriver;
    }

    /**
     * @param $sDriverName
     *
     * @return object|null
     */
    public function getDriver($sDriverName) {

        if (isset($this->aRegisteredDrivers[$sDriverName])) {
            if (!isset($this->aLoadedDrivers[$sDriverName])) {
                $oDriver = $this->LoadDriver($sDriverName);
                $this->aLoadedDrivers[$sDriverName] = $oDriver;
            }
            return $this->aLoadedDrivers[$sDriverName];
        }
        return null;
    }

    /**
     * Move temporary file to destination
     *
     * @param string $sTmpFile
     * @param string $sTargetFile
     *
     * @return bool
     */
    protected function MoveTmpFile($sTmpFile, $sTargetFile) {

        if (\F::File_Move($sTmpFile, $sTargetFile)) {
            return $sTargetFile;
        }
        $this->nLastError = self::ERR_MOVE_UPLOAD_FILE;
        return false;
    }

    /**
     * Return error code
     *
     * @return int
     */
    public function getError() {

        return $this->nLastError;
    }

    /**
     * Return error messge
     *
     * @param bool $bReset
     *
     * @return string
     */
    public function getErrorMsg($bReset = true) {

        if ($this->nLastError) {
            if (isset($this->aUploadErrors[$this->nLastError])) {
                $this->sLastError = $this->aUploadErrors[$this->nLastError];
            } else {
                $this->sLastError = 'Unknown error during file uploading';
            }
            $sError = $this->sLastError;
            if ($bReset) {
                $this->nLastError = 0;
            }
            return $sError;
        }
        return null;
    }

    /**
     * @param string $sFile
     * @param string $sConfigKey
     *
     * @return bool
     */
    protected function _checkUploadedImage($sFile, $sConfigKey = 'images.default') {

        $aInfo = @getimagesize($sFile);
        if (!$aInfo) {
            $this->nLastError = self::ERR_IMG_NO_INFO;
            return false;
        }
        // Gets local config
        if (!$sConfigKey) {
            $sConfigKey = 'images.default';
        } elseif (!strpos($sConfigKey, '.')) {
            $sConfigKey = 'images.' . $sConfigKey;
        }

        $aConfig = $this->aModConfig[$sConfigKey];
        if ($aConfig['max_width'] && F::MemSize2Int($aConfig['max_width']) < $aInfo[0]) {
            $this->nLastError = self::ERR_IMG_LARGE_WIDTH;
            return false;
        }
        if ($aConfig['max_height'] && F::MemSize2Int($aConfig['max_height']) < $aInfo[1]) {
            $this->nLastError = self::ERR_IMG_LARGE_HEIGHT;
            return false;
        }
        return true;
    }

    /**
     * @param string $sFile
     * @param string $sConfigKey
     *
     * @return bool
     */
    protected function _checkUploadedFile($sFile, $sConfigKey = 'default') {

        if (!$sConfigKey) {
            $sConfigKey = 'default';
        }
        $sExtension = $this->_extensionMime($sFile);
        $aConfig = $this->GetConfig($sFile, $sConfigKey);
        if (!$aConfig) {
            return false;
        }

        // Check allow extensions
        if ($aConfig['file_extensions'] && !in_array($sExtension, $aConfig['file_extensions'])) {
            $this->nLastError = self::ERR_NOT_ALLOWED_EXTENSION;
            return false;
        }
        // Check filesize
        if ($aConfig['file_maxsize'] && filesize($sFile) > F::MemSize2Int($aConfig['file_maxsize'])) {
            $this->nLastError = self::ERR_FILE_TOO_LARGE;
            return false;
        }

        // Check images
        if ($sConfigKey == 'default') {
            if (!empty($aConfig['image_extensions']) && in_array($sExtension, (array)$aConfig['image_extensions'])) {
                return $this->_checkUploadedImage($sFile, $sConfigKey);
            }
        } else {
            if (!empty($aConfig['image_extensions']) && strpos($sConfigKey, 'images.') === 0) {
                if (!in_array($sExtension, (array)$aConfig['image_extensions'])) {
                    $this->nLastError = self::ERR_IMG_NOT_ALLOWED_FORMAT;
                    return false;
                }
                return $this->_checkUploadedImage($sFile, $sConfigKey);
            }
        }

        return true;
    }

    /**
     * @param $sString
     *
     * @return string
     */
    protected function _extensionMime($sString) {

        if (strpos($sString, '.')) {
            $sResult = strtolower(pathinfo($sString, PATHINFO_EXTENSION));
        } else {
            $sResult = strtolower($sString);
        }
        return ($sResult == 'jpg' || $sResult == 'pjpeg') ? 'jpeg' : $sResult;
    }

    /**
     * Returns uploader config section for file by config key
     *
     * @param string $sFile
     * @param string $sConfigKey
     *
     * @return array
     */
    public function getConfig($sFile, $sConfigKey = 'default') {

        if (!$sConfigKey) {
            $sConfigKey = 'default';
        }
        $sExtension = $this->_extensionMime($sFile);
        if (!$sExtension) {
            $sExtension = '*';
        }
        $sTmpConfigKey = '-' . str_replace('.', '-', $sConfigKey) . '-' . $sExtension;
        $aConfig = $this->aModConfig[$sTmpConfigKey];

        if (is_null($aConfig)) {
            $aConfig = [];
            $aImageExtensions = [];

            // Gets local config
            if ($sConfigKey) {
                if (isset($this->aModConfig[$sConfigKey])) {
                    $aConfig = $this->aModConfig[$sConfigKey];
                    $aImageExtensions = (array)$aConfig['image_extensions'];
                } elseif (strpos($sConfigKey, '.')) {
                    if (strpos($sConfigKey, 'images.') === 0) {
                        $aConfig = $this->aModConfig['images.default'];
                        $aImageExtensions = (array)$aConfig['image_extensions'];
                    } else {
                        $sConfigKey = 'default';
                    }
                }
            }
            if (!$aConfig && $sConfigKey != 'default') {
                // Checks key 'images.<type>' and valid image extension
                if ($aConfig = $this->aModConfig['images.' . $sConfigKey]) {
                    if ($sExtension != '*') {
                        $aImageExtensions = (array)$aConfig['image_extensions'];
                        if (!$aImageExtensions || !in_array($sExtension, $aImageExtensions)) {
                            $aConfig = [];
                        }
                    }
                }
                // If this is not image then checks config for file specified type
                if (!$aConfig) {
                    $aConfig = $this->aModConfig['files.' . $sConfigKey];
                }
            }

            if (!$aConfig) {
                // Config section not found, sets default
                $aImageExtensions = (array)$this->aModConfig['images.default.image_extensions'];
                if ($aImageExtensions && in_array($sExtension, $aImageExtensions)) {
                    $aConfig = $this->aModConfig['images.default'];
                } else {
                    $aConfig = $this->aModConfig['files.default'];
                }
            }

            /* Copy MIME specified config into 'transform' section

             * INPUT:
             * $aConfig = array(
             *     'transform' => array(
             *         '@mime(jpeg,other)' => array(
             *             'quality' => 80,
             *         ),
             *     ),
             * );
             * $aImageExtensions = 'jpg';
             *
             * OUTPUT:
             * $aConfig = array(
             *     'transform' => array(
             *         'quality' => 80,
             *         'mime-jpeg,mime-jpg' => array(
             *             'quality' => 80,
             *         ),
             *     ),
             * );
             */
            if (($sExtension != '*') && $aImageExtensions && !empty($aConfig['transform'])) {
                foreach($aConfig['transform'] as $sKey => $aVal) {
                    if (strpos($sKey, '@mime(') === 0) {
                        $sMimeFound = null;
                        if (preg_match('/@mime\s*\(([\w,]+)\)/', $sKey, $aM)) {
                            $aKeys = F::Array_Str2Array($aM[1]);
                            foreach ($aKeys as $sMimeKey) {
                                $sMime = $this->_extensionMime($sMimeKey);
                                if ($sMime == $sExtension) {
                                    $sMimeFound = $sMime;
                                    break;
                                }
                            }
                        }
                        if ($sMimeFound) {
                            if (in_array($sMimeFound, $aImageExtensions)) {
                                foreach ($aVal as $sMimeCfgKey => $sMimeCfgVal) {
                                    $aConfig['transform'][$sMimeCfgKey] = $sMimeCfgVal;
                                }
                            }
                            break;
                        }
                    }
                }
            }
            $this->aModConfig[$sTmpConfigKey] = new DataArray($aConfig);
        }

        return $aConfig;
    }

    /**
     * Returns aspect ration from section 'transform'
     *
     * @param string $sFile
     * @param string $sConfigKey
     *
     * @return float|null
     */
    public function getConfigAspectRatio($sFile, $sConfigKey = 'default') {

        $aConfig = $this->GetConfig($sFile, $sConfigKey);
        $nResult = null;
        if (isset($aConfig['transform']['aspect_ratio'])) {
            $sAspectRatio = $aConfig['transform']['aspect_ratio'];
            if (strpos($sAspectRatio, ':')) {
                list($nW, $nH) = explode(':', $sAspectRatio, 2);
                $nResult = (float)$nW / (float)$nH;
            } else {
                $nResult = (float)$sAspectRatio;
            }
        }
        return $nResult;
    }

    /**
     * Upload file from client via HTTP POST
     *
     * @param array  $aFile
     * @param string $sTarget
     * @param string $sDir
     * @param bool   $bOriginalName
     *
     * @return bool|string
     */
    public function uploadLocal($aFile, $sTarget = 'default', $sDir = null, $bOriginalName = false)
    {
        $this->nLastError = 0;
        if (is_array($aFile) && isset($aFile['tmp_name'], $aFile['name'])) {
            if ($aFile['error'] === UPLOAD_ERR_OK) {
                if (is_uploaded_file($aFile['tmp_name'])) {
                    if ($bOriginalName) {
                        $sTmpFile = F::File_GetUploadDir() . $aFile['name'];
                    } else {
                        if (\E::userId()) {
                            $sExtension = dechex(\E::userId()) . '.' . pathinfo($aFile['name'], PATHINFO_EXTENSION);
                        } else {
                            $sExtension = pathinfo($aFile['name'], PATHINFO_EXTENSION);
                        }
                        $sTmpFile = strtolower(basename(\F::File_UploadUniqname($sExtension)));
                    }
                    // Copy uploaded file in our temp folder
                    if ($sTmpFile = F::File_MoveUploadedFile($aFile['tmp_name'], $sTmpFile)) {
                        if ($this->_checkUploadedFile($sTmpFile, $sTarget)) {
                            if ($sDir) {
                                $sTmpFile = $this->MoveTmpFile($sTmpFile, $sDir);
                            }
                            return $sTmpFile;
                        } else {
                            F::File_Delete($sTmpFile);
                        }
                    }
                } else {
                    // Файл не был загружен при помощи HTTP POST
                    $this->nLastError = self::ERR_NOT_POST_UPLOADED;
                }
            } else {
                // Ошибка загузки файла
                $this->nLastError = $aFile['error'];
            }
        } else {
            $this->nLastError = self::ERR_NOT_FILE_VARIABLE;
        }
        return false;
    }

    /**
     * Upload remote file by URL
     *
     * @param string $sUrl
     * @param string $sTarget
     * @param string $sDir
     * @param array  $aParams [max_size => «размер в килобайтах»]
     *
     * @return bool
     */
    public function uploadRemote($sUrl, $sTarget = 'default', $sDir = null, $aParams = [])
    {
        $this->nLastError = 0;
        if (!isset($aParams['max_size'])) {
            $aParams['max_size'] = 0;
        } else {
            $aParams['max_size'] = (int)$aParams['max_size'];
        }
        $sContent = '';
        if ($aParams['max_size']) {
            $hFile = @fopen($sUrl, 'r');
            if (!$hFile) {
                $this->nLastError = self::ERR_REMOTE_FILE_OPEN;
                return false;
            }

            $nSizeKb = 0;
            while (!feof($hFile) && $nSizeKb <= $aParams['max_size']) {
                $sPiece = fread($hFile, 1024);
                if ($sPiece) {
                    $nSizeKb += strlen($sPiece);
                    $sContent .= $sPiece;
                } else {
                    break;
                }
            }
            fclose($hFile);

            // * Если конец файла не достигнут, значит файл имеет недопустимый размер
            if ($nSizeKb > $aParams['max_size']) {
                $this->nLastError = self::ERR_REMOTE_FILE_MAXSIZE;
                return false;
            }
        } else {
            $sContent = @file_get_contents($sUrl);
            if ($sContent === false) {
                $this->nLastError = self::ERR_REMOTE_FILE_READ;
                return false;
            }
        }
        if ($sContent) {
            $sTmpFile = F::File_UploadUniqname(\F::File_GetExtension($sUrl));
            if (!F::File_PutContents($sTmpFile, $sContent)) {
                $this->nLastError = self::ERR_REMOTE_FILE_WRITE;
                return false;
            }
        }
        if (!empty($sTmpFile) && $this->_checkUploadedFile($sTmpFile, $sTarget)) {
            if ($sDir) {
                return $this->MoveTmpFile($sTmpFile, $sDir);
            }
            return $sTmpFile;
        }
        return false;
    }

    /**
     * @param string $sFilePath
     * @param string $sDestination
     * @param bool   $bRewrite
     *
     * @return string|bool
     */
    public function move($sFilePath, $sDestination, $bRewrite = true)
    {
        if ($sFilePath === $sDestination) {
            $sResult = $sDestination;
        } else {
            $sResult = F::File_Move($sFilePath, $sDestination, $bRewrite);
            if (!$sResult) {
                $this->nLastError = self::ERR_MOVE_UPLOAD_FILE;
            }
        }
        return $sResult;
    }

    /**
     * @param string $sFilePath
     * @param string $sDestination
     *
     * @return string|bool
     */
    public function copy($sFilePath, $sDestination)
    {
        if ($sFilePath === $sDestination) {
            $sResult = $sDestination;
        } else {
            $sResult = F::File_Copy($sFilePath, $sDestination);
            if (!$sResult) {
                $this->nLastError = self::ERR_COPY_UPLOAD_FILE;
            }
        }
        return $sResult;
    }

    /**
     * Path to user's upload dir
     *
     * @param int    $iUserId
     * @param string $sDir
     * @param bool   $bAutoMake
     *
     * @return string
     */
    protected function _getUserUploadDir($iUserId, $sDir, $bAutoMake = true)
    {
        $nMaxLen = 6;
        $nSplitLen = 2;
        $sPath = implode('/', str_split(str_pad($iUserId, $nMaxLen, '0', STR_PAD_LEFT), $nSplitLen));
        $sResult = F::File_NormPath(\F::File_RootDir() . $sDir . $sPath . '/');
        if ($bAutoMake) {
            F::File_CheckDir($sResult, $bAutoMake);
        }
        return $sResult;
    }

    /**
     * @param int  $iUserId
     * @param bool $bAutoMake
     *
     * @param bool $sType
     *
     * @return string
     */
    public function getUserImagesUploadDir($iUserId, $bAutoMake = TRUE, $sType = FALSE)
    {
//        return $this->_getUserUploadDir($nUserId, Config::Get('path.uploads.images'), $bAutoMake);
        $sDir = ($sType && ($sDir = \C::get('path.uploads.' . $sType))) ? $sDir : \C::get('path.uploads.images');

        return $this->_getUserUploadDir($iUserId, $sDir, $bAutoMake);
    }

    /**
     * @param int  $iUserId
     * @param bool $bAutoMake
     *
     * @return string
     */
    public function getUserFilesUploadDir($iUserId, $bAutoMake = true) {

        return $this->_getUserUploadDir($iUserId, \C::get('path.uploads.files'), $bAutoMake);
    }

    /**
     * Path to user's dir for avatars
     *
     * @param int  $iUserId
     * @param bool $bAutoMake
     *
     * @return string
     */
    public function getUserAvatarDir($iUserId, $bAutoMake = true) {

        $sResult = $this->GetUserImagesUploadDir($iUserId) . 'avatar/';
        if ($bAutoMake) {
            F::File_CheckDir($sResult, $bAutoMake);
        }
        return $sResult;
    }

    /**
     * Path to user's dir for uploaded images
     *
     * @param int         $iUserId
     * @param bool        $bAutoMake
     * @param string|bool $sType
     *
     * @return string
     */
    public function getUserImageDir($iUserId = null, $bAutoMake = true, $sType = false) {

        if (is_null($iUserId)) {
            $iUserId = (int)E::userId();
        }
        $sResult = $this->GetUserImagesUploadDir($iUserId, $bAutoMake, $sType) . date('Y/m/d/');
        if ($bAutoMake) {
            F::File_CheckDir($sResult, $bAutoMake);
        }
        return $sResult;
    }

    /**
     * @param int  $iUserId
     * @param bool $bAutoMake
     *
     * @return string
     */
    public function getUserFileDir($iUserId, $bAutoMake = true)
    {
        $sResult = $this->getUserFilesUploadDir($iUserId) . date('Y/m/d/');
        if ($bAutoMake) {
            F::File_CheckDir($sResult, $bAutoMake);
        }
        return $sResult;
    }

    /**
     * @param string $sDir
     * @param string $sExtension
     * @param int    $nLength
     *
     * @return mixed
     */
    public function uniqname($sDir, $sExtension, $nLength = 8)
    {
        return F::File_Uniqname($sDir, $sExtension, $nLength);
    }

    /**
     * @param string $sFile
     *
     * @return string
     */
    public function defineDriver(&$sFile)
    {
        if ($sFile[0] === '[' && ($n = strpos($sFile, ']'))) {
            $sDriver = substr($sFile, 1, $n - 1);
            if ($n === strlen($sFile)) {
                $sFile = '';
            } else {
                $sFile = substr($sFile, $n + 1);
            }
        } else {
            $sDriver = $this->sDefaultDriver;
        }
        return $sDriver;
    }

    /**
     * @param string $sFilePath
     *
     * @return bool|string
     */
    public function exists($sFilePath)
    {
        $sDriverName = $this->defineDriver($sFilePath);
        $oDriver = $this->getDriver($sDriverName);

        return $oDriver->Exists($sFilePath);
    }

    /**
     * Stores uploaded file
     *
     * @param string $sFile
     * @param string $sDestination
     * @param bool $bAddMresource
     *
     * @return bool|ModuleUploader_EntityItem
     */
    public function store($sFile, $sDestination = null, $bAddMresource = TRUE) {

        if (!$sDestination) {
            $sDriverName = $this->sDefaultDriver;
        } else {
            $sDriverName = $this->DefineDriver($sDestination);
        }
        if ($sDriverName) {
            /**
             * TODO: Создать интерфейс или абстрактный класс, от которого будут унаследованы все «драйверы»
             * @var $oDriver ModuleUploader_EntityDriverFile
             */
            $oDriver = $this->GetDriver($sDriverName);
            $oStoredItem = $oDriver->Store($sFile, $sDestination);
            if ($oStoredItem) {
                if (!$oStoredItem->GetUuid()) {
                    $oStoredItem->SetUuid($sDriverName);
                }
                /** @var ModuleMedia_EntityMedia $oMresource */
                $oMresource = \E::getEntity('Media', $oStoredItem);
                if ($bAddMresource) {
                    \E::Module('Media')->Add($oMresource);
                }
                return $oStoredItem;
            }
        }
        return false;
    }

    /**
     * Stores uploaded image with optional cropping
     *
     * @param  string $sFile - The server path to the temporary image file
     * @param  string $sTarget - Target type
     * @param  string $sTargetId - Target ID
     * @param  array|int|bool $xSize - The size of the area to cut the picture:
     *                               array('x1'=>0,'y1'=>0,'x2'=>100,'y2'=>100)
     *                               100 - crop 100x100 by center
     *                               true - crop square by min side
     *
     * @param bool $bMulti - Target has many images
     * @return bool|ModuleMedia_EntityMedia
     */
    public function storeImage($sFile, $sTarget, $sTargetId, $xSize = null, $bMulti = FALSE) {

        $oImg = \E::Module('Img')->Read($sFile);
        if (!$oImg) {
            // Возникла ошибка, надо обработать
            /** TODO Обработка ошибки */
            $this->nLastError = self::ERR_TRANSFORM_IMAGE;
            return false;
        }

        $sExtension = strtolower(pathinfo($sFile, PATHINFO_EXTENSION));
        $aConfig = $this->GetConfig($sFile, 'images.' . $sTarget);

        // Check whether to save the original
        if (isset($aConfig['original']['save']) && $aConfig['original']['save']) {
            $sSuffix = (isset($aConfig['original']['suffix']) ? $aConfig['original']['suffix'] : '-original');
            $sOriginalFile = F::File_Copy($sFile, $sFile . $sSuffix . '.' . $sExtension);
        } else {
            $sOriginalFile = null;
        }

        if (!is_null($xSize)) {
            if ($xSize === true) {
                // crop square by min side
                $oImg = \E::Module('Img')->CropSquare($oImg, TRUE);
            } elseif(is_numeric($xSize)) {
                // crop square in center
                $oImg = \E::Module('Img')->CropCenter($oImg, (int)$xSize, (int)$xSize);
            } elseif (is_array($xSize) && !empty($xSize)) {
                if (!isset($xSize['w']) && isset($xSize['x1'], $xSize['x2'])) {
                    $xSize['w'] = $xSize['x2'] - $xSize['x1'];
                }
                if (!isset($xSize['h']) && isset($xSize['y1'], $xSize['y2'])) {
                    $xSize['h'] = $xSize['y2'] - $xSize['y1'];
                }
                if (isset($xSize['w']) && !isset($xSize['h'])) {
                    $xSize['h'] = $oImg->getHeight();
                }
                if (!isset($xSize['w']) && isset($xSize['h'])) {
                    $xSize['w'] = $oImg->getWidth();
                }
                if ((isset($xSize['w']) && isset($xSize['h'])) && !(isset($xSize['x1']) && isset($xSize['y1']))) {
                    $oImg = \E::Module('Img')->CropCenter($oImg, $xSize['w'], $xSize['h']);
                } else {
                    $oImg = \E::Module('Img')->Crop($oImg, $xSize['w'], $xSize['h'], $xSize['x1'], $xSize['y1']);
                }
            }
        }

        if ($aConfig['transform']) {
            \E::Module('Img')->Transform($oImg, $aConfig['transform']);
        }

        // Сохраняем изображение во временный файл
        if ($sTmpFile = $oImg->Save(\F::File_UploadUniqname($sExtension))) {

            // Файл, куда будет записано изображение
            $sImageFile = $this->Uniqname(\E::Module('Uploader')->getUserImageDir(\E::userId(), true, false), $sExtension);

            // Окончательная запись файла
            if ($oStoredFile = $this->Store($sTmpFile, $sImageFile)) {

                $oResource = \E::Module('Media')->getMresourcesByUuid($oStoredFile->getUuid());
                $aTmpTarget = array(
                    'topic_comment',
                    'talk_comment',
                    'talk',
                );
                if (!(in_array($sTarget, $aTmpTarget) && !$sTargetId)) {
                    if (!$this->AddRelationResourceTarget($oResource, $sTarget, $sTargetId, $bMulti)) {
                        // TODO Возможная ошибка
                    }
                }

                return $oStoredFile;
            }
        }
        return false;
    }

    /**
     * @param string                $sTargetType
     * @param int                   $iTargetId
     * @param ModuleUser_EntityUser $oCurrentUser
     */
    public function deleteImage($sTargetType, $iTargetId, $oCurrentUser) {

        if ($sTargetType == 'profile_avatar') {
            if ($oCurrentUser && $oCurrentUser->getid() == $iTargetId) {
                $oUser = $oCurrentUser;
            } else {
                $oUser = \E::Module('User')->getUserById($iTargetId);
            }
            \E::Module('User')->DeleteAvatar($oUser);
        } elseif ($sTargetType == 'profile_photo') {
            if ($oCurrentUser && $oCurrentUser->getid() == $iTargetId) {
                $oUser = $oCurrentUser;
            } else {
                $oUser = \E::Module('User')->getUserById($iTargetId);
            }
            \E::Module('User')->DeletePhoto($oUser);
        } elseif ($sTargetType == 'blog_avatar') {
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = \E::Module('Blog')->getBlogById($iTargetId);
            \E::Module('Blog')->DeleteAvatar($oBlog);
        }
        \E::Module('Media')->UnlinkFile($sTargetType, $iTargetId, $oCurrentUser ? $oCurrentUser->getId() : 0);
    }

    /**
     * Добавляет связь между ресурсом и целевым объектом
     *
     * @param ModuleMedia_EntityMedia $oResource
     * @param string $sTargetType
     * @param string $sTargetId
     * @param bool   $bMulti
     *
     * @return bool|ModuleMedia_EntityMedia
     */
    public function addRelationResourceTarget($oResource, $sTargetType, $sTargetId, $bMulti = FALSE) {

        if ($oResource) {
            // Если одиночная загрузка, то предыдущий файл затрем
            // Иначе просто добавляем еще один.
            if (!$bMulti) {
                \E::Module('Media')->UnlinkFile($sTargetType, $sTargetId, E::userId());
            }

            $oResource->setUrl(\E::Module('Media')->normalizeUrl($this->GetTargetUrl($sTargetType, $sTargetId)));
            $oResource->setType($sTargetType);
            $oResource->setUserId(\E::userId());
            if ($sTargetId == '0') {
                $oResource->setTargetTmp(\E::Module('Session')->getCookie(self::COOKIE_TARGET_TMP));
            }
            \E::Module('Media')->AddTargetRel(array($oResource), $sTargetType, $sTargetId);

            return $oResource;
        }

        return FALSE;
    }

    /**
     * @param string $sFilePath
     *
     * @return bool
     */
    public function delete($sFilePath) {

        $sDriverName = $this->DefineDriver($sFilePath);
        $oDriver = $this->GetDriver($sDriverName);
        return $oDriver->Delete($sFilePath);
    }

    /**
     * @param string $sFilePath
     *
     * @return bool
     */
    public function deleteAs($sFilePath) {

        $sDriverName = $this->DefineDriver($sFilePath);
        $oDriver = $this->GetDriver($sDriverName);
        return $oDriver->Delete($sFilePath);
    }

    /**
     * @param string $sFilePath
     *
     * @return string
     */
    public function dir2Url($sFilePath) {

        if ($sFilePath[0] === '@') {
            return \C::get('path.root.url') . substr($sFilePath, 1);
        }

        $sDriverName = $this->defineDriver($sFilePath);
        $oDriver = $this->getDriver($sDriverName);

        return $oDriver->Dir2Url($sFilePath);
    }

    /**
     * @param string $sUrl
     *
     * @return string|bool
     */
    public function url2Dir($sUrl)
    {
        if ($sUrl[0] === '@') {
            return \C::get('path.root.dir') . substr($sUrl, 1);
        }

        $aDrivers = $this->GetRegisteredDrivers();
        foreach ($aDrivers as $sDriver) {
            $oDriver = $this->GetDriver($sDriver);
            $sFile = $oDriver->Url2Dir($sUrl);
            if ($sFile) {
                return $sFile;
            }
        }
        return false;
    }

    /**
     * @param $sPath
     * @param $sResultPath
     *
     * @return bool|string
     */
    protected function _getDrive($sPath, &$sResultPath)
    {
        if ($sPath && is_string($sPath) && strlen($sPath) > 1) {
            if ($sPath[0] === '@') {
                $sDrive = 'local';
                $sResultPath = substr($sPath, 1);
            } elseif ($sPath[0] === '[' && ($nPos = strpos($sPath, ']'))) {
                $sDrive = substr($sPath, 1, $nPos - 1);
                $sResultPath = substr($sPath, $nPos + 1);
            } else {
                $sDrive = '';
                $sResultPath = $sPath;
            }
            return $sDrive;
        }
        return false;
    }

    /**
     * @param $sUrl
     *
     * @return bool
     */
    public function completeUrl($sUrl) {

        $sDrive = $this->_getDrive($sUrl, $sPath);
        if ($sDrive) {
            $sRootUrl = C::get('module.uploader.drives.' . $sDrive . '.url');
            // Old version compatibility
            if (!$sRootUrl && $sDrive === 'local') {
                $sRootUrl = C::get('path.root.url');
            }
            if ($sRootUrl) {
                return F::File_NormPath($sRootUrl . '/' . $sPath);
            }
        }
        return $sUrl;
    }

    /**
     * @param $sDir
     *
     * @return bool
     */
    public function completeDir($sDir) {

        $sDrive = $this->_getDrive($sDir, $sPath);
        if ($sDrive) {
            $sRootDir = C::get('module.uploader.drives.' . $sDrive . '.dir');
            // Old version compatibility
            if (!$sRootDir && $sDrive === 'local') {
                $sRootDir = C::get('path.root.dir');
            }
            if ($sRootDir) {
                return F::File_NormPath($sRootDir . '/' . $sPath);
            }
        }
        return $sDir;
    }

    /**
     * Возвращает максимальное количество картинок для типа объекта
     *
     * @param string $sTargetType
     * @param bool   $sTargetId
     *
     * @return bool
     */
    public function getAllowedCount($sTargetType, $sTargetId = FALSE)
    {
        if ($sTargetType === 'photoset') {
            if ($iMaxCount = (int)\C::get('module.topic.photoset.count_photos_max')) {
                $aPhotoSetData = \E::Module('Media')->getPhotosetData($sTargetType, (int)$sTargetId);
                return $aPhotoSetData['count'] < $iMaxCount;
            } else {
                // If max number is not defined then without limitations
                return TRUE;
            }
        }
        if ($sTargetType === 'topic') {
            return TRUE;
        }

        if ($sTargetType === 'topic_comment') {
            return TRUE;
        }

        if ($sTargetType === 'talk_comment') {
            return TRUE;
        }

        if ($sTargetType === 'talk') {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Проверяет доступность того или иного целевого объекта, переопределяется
     * плагинами. По умолчанию всё грузить запрещено.
     * Если всё нормально и пользователю разрешено сюда загружать картинки,
     * то метод возвращает целевой объект, иначе значение FALSE.
     *
     * @param string $sTarget
     * @param int    $iTargetId
     *
     * @return mixed
     */
    public function checkAccessAndGetTarget($sTarget, $iTargetId = null) {

        // Проверяем право пользователя на прикрепление картинок к топику
        if (mb_strpos($sTarget, 'single-image-uploader') === 0 || $sTarget == 'photoset') {

            // Проверям, авторизован ли пользователь
            if (!\E::isUser()) {
                return FALSE;
            }

            // Топик редактируется
            if ($oTopic = \E::Module('Topic')->getTopicById($iTargetId)) {
                if (!\E::Module('ACL')->isAllowEditTopic($oTopic, E::User())) {
                    return FALSE;
                }
                return $oTopic;
            }

            return TRUE;
        }

        // Загружать аватарки можно только в свой профиль
        if ($sTarget == 'profile_avatar') {

            if ($iTargetId && E::isUser() && $iTargetId == \E::userId()) {
                return E::User();
            }

            return FALSE;
        }

        // Загружать аватарки можно только в свой профиль
        if ($sTarget == 'profile_photo') {

            if ($iTargetId && E::isUser() && $iTargetId == \E::userId()) {
                return E::User();
            }

            return FALSE;
        }

        if ($sTarget == 'blog_avatar') {
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = \E::Module('Blog')->getBlogById($iTargetId);

            if (!\E::isUser()) {
                return false;
            }

            if (!$oBlog) {
                // Блог еще не создан
                return (\E::Module('ACL')->canCreateBlog(\E::User()) || E::isAdminOrModerator());
            }

            if ($oBlog && (\E::Module('ACL')->CheckBlogEditBlog($oBlog, E::User()) || E::isAdminOrModerator())) {
                return $oBlog;
            }

            return '';
        }

        if ($sTarget == 'topic') {
            if (!\E::isUser()) {
                return false;
            }
            /** @var ModuleTopic_EntityTopic $oTopic */
            $oTopic = \E::Module('Topic')->getTopicById($iTargetId);

            if (!$oTopic) {
                // Топик еще не создан
                return TRUE;
            }

            if ($oTopic && (\E::Module('ACL')->isAllowEditTopic($oTopic, E::User()) || E::isAdminOrModerator())) {
                return $oTopic;
            }

            return '';
        }

        if ($sTarget == 'topic_comment') {
            if (!\E::isUser()) {
                return false;
            }
            /** @var ModuleComment_EntityComment $oComment */
            $oComment = \E::Module('Comment')->getCommentById($iTargetId);

            if (!$oComment) {
                // Комментарий еще не создан
                return TRUE;
            }

            if ($oComment && ((\E::Module('ACL')->canPostComment(\E::User(), $oComment->getTarget()) && \E::Module('Acl')->CanPostCommentTime(\E::User())) || E::isAdminOrModerator())) {
                return $oComment;
            }

            return '';
        }

        if ($sTarget == 'talk_comment') {
            if (!\E::isUser()) {
                return false;
            }
            /** @var ModuleComment_EntityComment $oComment */
            $oComment = \E::Module('Comment')->getCommentById($iTargetId);

            if (!$oComment) {
                // Комментарий еще не создан
                return TRUE;
            }

            if ($oComment && (\E::Module('Acl')->CanPostTalkCommentTime(\E::User()) || E::isAdminOrModerator())) {
                return $oComment;
            }

            return '';
        }

        if ($sTarget == 'talk') {
            if (!\E::isUser()) {
                return false;
            }
            /** @var ModuleComment_EntityComment $oTalk */
            $oTalk = \E::Module('Talk')->getTalkById($iTargetId);

            if (!$oTalk) {
                // Комментарий еще не создан
                return TRUE;
            }

            if ($oTalk && (\E::Module('Acl')->CanSendTalkTime(\E::User()) || E::isAdminOrModerator())) {
                return $oTalk;
            }

            return '';
        }

        return FALSE;
    }

    /**
     * Получает URL цели
     *
     * @param string $sTargetType
     * @param int    $iTargetId
     *
     * @return string
     */
    public function getTargetUrl($sTargetType, $iTargetId) {

        if (mb_strpos($sTargetType, 'single-image-uploader') === 0 || $sTargetType == 'photoset' || $sTargetType == 'topic') {
            /** @var ModuleTopic_EntityTopic $oTopic */
            if (!$oTopic = \E::Module('Topic')->getTopicById($iTargetId)) {
                return '';
            }

            return $oTopic->getUrl();
        }

        if ($sTargetType == 'profile_avatar') {
            return R::getLink('settings');
        }

        if ($sTargetType == 'profile_photo') {
            return R::getLink('settings');
        }

        if ($sTargetType == 'blog_avatar') {
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = \E::Module('Blog')->getBlogById($iTargetId);
            if ($oBlog) {
                return $oBlog->getUrlFull();
            }
            return '';
        }

        return '';
    }

    /**
     * Получает урл изображения целевого объекта
     *
     * @param string      $sTargetType
     * @param int         $iTargetId
     * @param bool|string $xSize
     *
     * @return string
     */
    public function getTargetImageUrl($sTargetType, $iTargetId, $xSize=FALSE) {

        $aMResourceRel = \E::Module('Media')->getMresourcesRelByTarget($sTargetType, $iTargetId);
        if ($aMResourceRel) {
            $oMResource = reset($aMResourceRel);

            $sUrl = $this->CompleteUrl($oMResource->getPathUrl());
            if (!$xSize) {
                return $sUrl;
            }

            return $this->ResizeTargetImage($sUrl, $xSize);
        }

        return '';

    }

    /**
     * Возвращает URL изображения по новому размеру
     *
     * @param string $sOriginalPath
     * @param string $xSize
     *
     * @return string
     */
    public function resizeTargetImage($sOriginalPath, $xSize) {

        $sModSuffix = F::File_ImgModSuffix($xSize, pathinfo($sOriginalPath, PATHINFO_EXTENSION));
        $sUrl = $sOriginalPath . $sModSuffix;

        if (\C::get('module.image.autoresize')) {
            $sFile = $this->Url2Dir($sUrl);
            if ($sFile && !F::File_Exists($sFile)) {
                \E::Module('Img')->Duplicate($sFile);
            }
        }
        $sUrl = $this->CompleteUrl($sUrl);

        return $sUrl;
    }

    /**
     * @param string         $sTargetType
     * @param int|array|null $xTargetId
     * @param int|array|null $xUsers
     * @param array|null     $aStructurize
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getTargetImages($sTargetType, $xTargetId = null, $xUsers = null, $aStructurize = null)
    {
        $aMResourceRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, $xTargetId, $xUsers);

        if ($aMResourceRel && $aStructurize) {
            if (!is_array($aStructurize)) {
                $aStructurize = array($aStructurize);
            }
            $aMResourceRel =  \E::Module('Media')->structurize($aMResourceRel, $aStructurize);
        }
        return $aMResourceRel;
    }

    /**
     * @param string         $sTargetType
     * @param int|array|null $xTargetId
     * @param int|array|null $xUsers
     * @param array|null     $aStructurize
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getMediaObjects($sTargetType, $xTargetId = null, $xUsers = null, $aStructurize = null) {

        $aMResourceRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, $xTargetId, $xUsers);

        if ($aMResourceRel && $aStructurize) {
            if (!is_array($aStructurize)) {
                $aStructurize = array($aStructurize);
            }
            $aMResourceRel =  \E::Module('Media')->structurize($aMResourceRel, $aStructurize);
        }
        return $aMResourceRel;
    }

    /**
     * @param $xUsers
     * @param $sTargetType
     * @param $xTargetId
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getImagesByUserAndTarget($xUsers, $sTargetType, $xTargetId = null) {

        $aUserIds = $this->_entitiesId($xUsers);
        $aMResourceRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, $xTargetId, $aUserIds);

        $aResult = array_fill_keys($aUserIds, array());
        if ($aMResourceRel) {
            foreach ($aMResourceRel as $oMResourseRel) {
                $aResult[$oMResourseRel->getUserId()][$oMResourseRel->getId()] = $oMResourseRel;
            }
        }

        return $aResult;
    }

    public function getImagesByCriteria() {

    }

}

// EOF
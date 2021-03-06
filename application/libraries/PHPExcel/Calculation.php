<?php

	private function __construct(PHPExcel $workbook = NULL) {
		$setPrecision = (PHP_INT_SIZE == 4) ? 14 : 16;
		$this->_savedPrecision = ini_get('precision');
		if ($this->_savedPrecision < $setPrecision) {
			ini_set('precision',$setPrecision);
		}

		if ($workbook !== NULL) {
			self::$_workbookSets[$workbook->getID()] = $this;
		}

		$this->_workbook = $workbook;
		$this->_cyclicReferenceStack = new PHPExcel_CalcEngine_CyclicReferenceStack();
	    $this->_debugLog = new PHPExcel_CalcEngine_Logger($this->_cyclicReferenceStack);
	}


	public function __destruct() {
		if ($this->_savedPrecision != ini_get('precision')) {
			ini_set('precision',$this->_savedPrecision);
		}
	}

	private static function _loadLocales() {
		$localeFileDirectory = PHPEXCEL_ROOT.'PHPExcel/locale/';
		foreach (glob($localeFileDirectory.'/*',GLOB_ONLYDIR) as $filename) {
			$filename = substr($filename,strlen($localeFileDirectory)+1);
			if ($filename != 'en') {
				self::$_validLocaleLanguages[] = $filename;
			}
		}
	}

	/**
	 * Get an instance of this class
	 *
	 * @access	public
	 * @param   PHPExcel $workbook  Injected workbook for working with a PHPExcel object,
	 *									or NULL to create a standalone claculation engine
	 * @return PHPExcel_Calculation
	 */
	public static function getInstance(PHPExcel $workbook = NULL) {
		if ($workbook !== NULL) {
    		if (isset(self::$_workbookSets[$workbook->getID()])) {
    			return self::$_workbookSets[$workbook->getID()];
    		}
			return new PHPExcel_Calculation($workbook);
		}

		if (!isset(self::$_instance) || (self::$_instance === NULL)) {
			self::$_instance = new PHPExcel_Calculation();
		}

		return self::$_instance;
	}	//	function getInstance()

	/**
	 * Unset an instance of this class
	 *
	 * @access	public
	 * @param   PHPExcel $workbook  Injected workbook identifying the instance to unset
	 */
	public static function unsetInstance(PHPExcel $workbook = NULL) {
		if ($workbook !== NULL) {
    		if (isset(self::$_workbookSets[$workbook->getID()])) {
    			unset(self::$_workbookSets[$workbook->getID()]);
    		}
		}
    }

	/**
	 * Flush the calculation cache for any existing instance of this class
	 *		but only if a PHPExcel_Calculation instance exists
	 *
	 * @access	public
	 * @return null
	 */
	public function flushInstance() {
		$this->clearCalculationCache();
	}	//	function flushInstance()


	/**
	 * Get the debuglog for this claculation engine instance
	 *
	 * @access	public
	 * @return PHPExcel_CalcEngine_Logger
	 */
	public function getDebugLog() {
		return $this->_debugLog;
	}

	/**
	 * __clone implementation. Cloning should not be allowed in a Singleton!
	 *
	 * @access	public
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public final function __clone() {
		throw new PHPExcel_Calculation_Exception ('Cloning the calculation engine is not allowed!');
	}	//	function __clone()


	/**
	 * Return the locale-specific translation of TRUE
	 *
	 * @access	public
	 * @return	 string		locale-specific translation of TRUE
	 */
	public static function getTRUE() {
		return self::$_localeBoolean['TRUE'];
	}

	/**
	 * Return the locale-specific translation of FALSE
	 *
	 * @access	public
	 * @return	 string		locale-specific translation of FALSE
	 */
	public static function getFALSE() {
		return self::$_localeBoolean['FALSE'];
	}

	/**
	 * Set the Array Return Type (Array or Value of first element in the array)
	 *
	 * @access	public
	 * @param	 string	$returnType			Array return type
	 * @return	 boolean					Success or failure
	 */
	public static function setArrayReturnType($returnType) {
		if (($returnType == self::RETURN_ARRAY_AS_VALUE) ||
			($returnType == self::RETURN_ARRAY_AS_ERROR) ||
			($returnType == self::RETURN_ARRAY_AS_ARRAY)) {
			self::$returnArrayAsType = $returnType;
			return TRUE;
		}
		return FALSE;
	}	//	function setArrayReturnType()


	/**
	 * Return the Array Return Type (Array or Value of first element in the array)
	 *
	 * @access	public
	 * @return	 string		$returnType			Array return type
	 */
	public static function getArrayReturnType() {
		return self::$returnArrayAsType;
	}	//	function getArrayReturnType()


	/**
	 * Is calculation caching enabled?
	 *
	 * @access	public
	 * @return boolean
	 */
	public function getCalculationCacheEnabled() {
		return $this->_calculationCacheEnabled;
	}	//	function getCalculationCacheEnabled()

	/**
	 * Enable/disable calculation cache
	 *
	 * @access	public
	 * @param boolean $pValue
	 */
	public function setCalculationCacheEnabled($pValue = TRUE) {
		$this->_calculationCacheEnabled = $pValue;
		$this->clearCalculationCache();
	}	//	function setCalculationCacheEnabled()


	/**
	 * Enable calculation cache
	 */
	public function enableCalculationCache() {
		$this->setCalculationCacheEnabled(TRUE);
	}	//	function enableCalculationCache()


	/**
	 * Disable calculation cache
	 */
	public function disableCalculationCache() {
		$this->setCalculationCacheEnabled(FALSE);
	}	//	function disableCalculationCache()


	/**
	 * Clear calculation cache
	 */
	public function clearCalculationCache() {
		$this->_calculationCache = array();
	}	//	function clearCalculationCache()

	/**
	 * Clear calculation cache for a specified worksheet
	 *
	 * @param string $worksheetName
	 */
	public function clearCalculationCacheForWorksheet($worksheetName) {
		if (isset($this->_calculationCache[$worksheetName])) {
			unset($this->_calculationCache[$worksheetName]);
		}
	}	//	function clearCalculationCacheForWorksheet()

	/**
	 * Rename calculation cache for a specified worksheet
	 *
	 * @param string $fromWorksheetName
	 * @param string $toWorksheetName
	 */
	public function renameCalculationCacheForWorksheet($fromWorksheetName, $toWorksheetName) {
		if (isset($this->_calculationCache[$fromWorksheetName])) {
			$this->_calculationCache[$toWorksheetName] = &$this->_calculationCache[$fromWorksheetName];
			unset($this->_calculationCache[$fromWorksheetName]);
		}
	}	//	function renameCalculationCacheForWorksheet()


	/**
	 * Get the currently defined locale code
	 *
	 * @return string
	 */
	public function getLocale() {
		return self::$_localeLanguage;
	}	//	function getLocale()


	/**
	 * Set the locale code
	 *
	 * @param string $locale  The locale to use for formula translation
	 * @return boolean
	 */
	public function setLocale($locale = 'en_us') {
		//	Identify our locale and language
		$language = $locale = strtolower($locale);
		if (strpos($locale,'_') !== FALSE) {
			list($language) = explode('_',$locale);
		}

		if (count(self::$_validLocaleLanguages) == 1)
			self::_loadLocales();

		//	Test whether we have any language data for this language (any locale)
		if (in_array($language,self::$_validLocaleLanguages)) {
			//	initialise language/locale settings
			self::$_localeFunctions = array();
			self::$_localeArgumentSeparator = ',';
			self::$_localeBoolean = array('TRUE' => 'TRUE', 'FALSE' => 'FALSE', 'NULL' => 'NULL');
			//	Default is English, if user isn't requesting english, then read the necessary data from the locale files
			if ($locale != 'en_us') {
				//	Search for a file with a list of function names for locale
				$functionNamesFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.str_replace('_',DIRECTORY_SEPARATOR,$locale).DIRECTORY_SEPARATOR.'functions';
				if (!file_exists($functionNamesFile)) {
					//	If there isn't a locale specific function file, look for a language specific function file
					$functionNamesFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.'functions';
					if (!file_exists($functionNamesFile)) {
						return FALSE;
					}
				}
				//	Retrieve the list of locale or language specific function names
				$localeFunctions = file($functionNamesFile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($localeFunctions as $localeFunction) {
					list($localeFunction) = explode('##',$localeFunction);	//	Strip out comments
					if (strpos($localeFunction,'=') !== FALSE) {
						list($fName,$lfName) = explode('=',$localeFunction);
						$fName = trim($fName);
						$lfName = trim($lfName);
						if ((isset(self::$_PHPExcelFunctions[$fName])) && ($lfName != '') && ($fName != $lfName)) {
							self::$_localeFunctions[$fName] = $lfName;
						}
					}
				}
				//	Default the TRUE and FALSE constants to the locale names of the TRUE() and FALSE() functions
				if (isset(self::$_localeFunctions['TRUE'])) { self::$_localeBoolean['TRUE'] = self::$_localeFunctions['TRUE']; }
				if (isset(self::$_localeFunctions['FALSE'])) { self::$_localeBoolean['FALSE'] = self::$_localeFunctions['FALSE']; }

				$configFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.str_replace('_',DIRECTORY_SEPARATOR,$locale).DIRECTORY_SEPARATOR.'config';
				if (!file_exists($configFile)) {
					$configFile = PHPEXCEL_ROOT . 'PHPExcel'.DIRECTORY_SEPARATOR.'locale'.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.'config';
				}
				if (file_exists($configFile)) {
					$localeSettings = file($configFile,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					foreach ($localeSettings as $localeSetting) {
						list($localeSetting) = explode('##',$localeSetting);	//	Strip out comments
						if (strpos($localeSetting,'=') !== FALSE) {
							list($settingName,$settingValue) = explode('=',$localeSetting);
							$settingName = strtoupper(trim($settingName));
							switch ($settingName) {
								case 'ARGUMENTSEPARATOR' :
									self::$_localeArgumentSeparator = trim($settingValue);
									break;
							}
						}
					}
				}
			}

			self::$functionReplaceFromExcel = self::$functionReplaceToExcel =
			self::$functionReplaceFromLocale = self::$functionReplaceToLocale = NULL;
			self::$_localeLanguage = $locale;
			return TRUE;
		}
		return FALSE;
	}	//	function setLocale()



	public static function _translateSeparator($fromSeparator,$toSeparator,$formula,&$inBraces) {
		$strlen = mb_strlen($formula);
		for ($i = 0; $i < $strlen; ++$i) {
			$chr = mb_substr($formula,$i,1);
			switch ($chr) {
				case '{' :	$inBraces = TRUE;
							break;
				case '}' :	$inBraces = FALSE;
							break;
				case $fromSeparator :
							if (!$inBraces) {
								$formula = mb_substr($formula,0,$i).$toSeparator.mb_substr($formula,$i+1);
							}
			}
		}
		return $formula;
	}

	private static function _translateFormula($from,$to,$formula,$fromSeparator,$toSeparator) {
		//	Convert any Excel function names to the required language
		if (self::$_localeLanguage !== 'en_us') {
			$inBraces = FALSE;
			//	If there is the possibility of braces within a quoted string, then we don't treat those as matrix indicators
			if (strpos($formula,'"') !== FALSE) {
				//	So instead we skip replacing in any quoted strings by only replacing in every other array element after we've exploded
				//		the formula
				$temp = explode('"',$formula);
				$i = FALSE;
				foreach($temp as &$value) {
					//	Only count/replace in alternating array entries
					if ($i = !$i) {
						$value = preg_replace($from,$to,$value);
						$value = self::_translateSeparator($fromSeparator,$toSeparator,$value,$inBraces);
					}
				}
				unset($value);
				//	Then rebuild the formula string
				$formula = implode('"',$temp);
			} else {
				//	If there's no quoted strings, then we do a simple count/replace
				$formula = preg_replace($from,$to,$formula);
				$formula = self::_translateSeparator($fromSeparator,$toSeparator,$formula,$inBraces);
			}
		}

		return $formula;
	}

	private static $functionReplaceFromExcel	= NULL;
	private static $functionReplaceToLocale		= NULL;

	public function _translateFormulaToLocale($formula) {
		if (self::$functionReplaceFromExcel === NULL) {
			self::$functionReplaceFromExcel = array();
			foreach(array_keys(self::$_localeFunctions) as $excelFunctionName) {
				self::$functionReplaceFromExcel[] = '/(@?[^\w\.])'.preg_quote($excelFunctionName).'([\s]*\()/Ui';
			}
			foreach(array_keys(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceFromExcel[] = '/(@?[^\w\.])'.preg_quote($excelBoolean).'([^\w\.])/Ui';
			}

		}

		if (self::$functionReplaceToLocale === NULL) {
			self::$functionReplaceToLocale = array();
			foreach(array_values(self::$_localeFunctions) as $localeFunctionName) {
				self::$functionReplaceToLocale[] = '$1'.trim($localeFunctionName).'$2';
			}
			foreach(array_values(self::$_localeBoolean) as $localeBoolean) {
				self::$functionReplaceToLocale[] = '$1'.trim($localeBoolean).'$2';
			}
		}

		return self::_translateFormula(self::$functionReplaceFromExcel,self::$functionReplaceToLocale,$formula,',',self::$_localeArgumentSeparator);
	}	//	function _translateFormulaToLocale()


	private static $functionReplaceFromLocale	= NULL;
	private static $functionReplaceToExcel		= NULL;

	public function _translateFormulaToEnglish($formula) {
		if (self::$functionReplaceFromLocale === NULL) {
			self::$functionReplaceFromLocale = array();
			foreach(array_values(self::$_localeFunctions) as $localeFunctionName) {
				self::$functionReplaceFromLocale[] = '/(@?[^\w\.])'.preg_quote($localeFunctionName).'([\s]*\()/Ui';
			}
			foreach(array_values(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceFromLocale[] = '/(@?[^\w\.])'.preg_quote($excelBoolean).'([^\w\.])/Ui';
			}
		}

		if (self::$functionReplaceToExcel === NULL) {
			self::$functionReplaceToExcel = array();
			foreach(array_keys(self::$_localeFunctions) as $excelFunctionName) {
				self::$functionReplaceToExcel[] = '$1'.trim($excelFunctionName).'$2';
			}
			foreach(array_keys(self::$_localeBoolean) as $excelBoolean) {
				self::$functionReplaceToExcel[] = '$1'.trim($excelBoolean).'$2';
			}
		}

		return self::_translateFormula(self::$functionReplaceFromLocale,self::$functionReplaceToExcel,$formula,self::$_localeArgumentSeparator,',');
	}	//	function _translateFormulaToEnglish()


	public static function _localeFunc($function) {
		if (self::$_localeLanguage !== 'en_us') {
			$functionName = trim($function,'(');
			if (isset(self::$_localeFunctions[$functionName])) {
				$brace = ($functionName != $function);
				$function = self::$_localeFunctions[$functionName];
				if ($brace) { $function .= '('; }
			}
		}
		return $function;
	}




	/**
	 * Wrap string values in quotes
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function _wrapResult($value) {
		if (is_string($value)) {
			//	Error values cannot be "wrapped"
			if (preg_match('/^'.self::CALCULATION_REGEXP_ERROR.'$/i', $value, $match)) {
				//	Return Excel errors "as is"
				return $value;
			}
			//	Return strings wrapped in quotes
			return '"'.$value.'"';
		//	Convert numeric errors to NaN error
		} else if((is_float($value)) && ((is_nan($value)) || (is_infinite($value)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}

		return $value;
	}	//	function _wrapResult()


	/**
	 * Remove quotes used as a wrapper to identify string values
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function _unwrapResult($value) {
		if (is_string($value)) {
			if ((isset($value[0])) && ($value[0] == '"') && (substr($value,-1) == '"')) {
				return substr($value,1,-1);
			}
		//	Convert numeric errors to NaN error
		} else if((is_float($value)) && ((is_nan($value)) || (is_infinite($value)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}
		return $value;
	}	//	function _unwrapResult()




	/**
	 * Calculate cell value (using formula from a cell ID)
	 * Retained for backward compatibility
	 *
	 * @access	public
	 * @param	PHPExcel_Cell	$pCell	Cell to calculate
	 * @return	mixed
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function calculate(PHPExcel_Cell $pCell = NULL) {
		try {
			return $this->calculateCellValue($pCell);
		} catch (PHPExcel_Exception $e) {
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}
	}	//	function calculate()


	/**
	 * Calculate the value of a cell formula
	 *
	 * @access	public
	 * @param	PHPExcel_Cell	$pCell		Cell to calculate
	 * @param	Boolean			$resetLog	Flag indicating whether the debug log should be reset or not
	 * @return	mixed
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function calculateCellValue(PHPExcel_Cell $pCell = NULL, $resetLog = TRUE) {
		if ($pCell === NULL) {
			return NULL;
		}

		$returnArrayAsType = self::$returnArrayAsType;
		if ($resetLog) {
			//	Initialise the logging settings if requested
			$this->formulaError = null;
			$this->_debugLog->clearLog();
			$this->_cyclicReferenceStack->clear();
			$this->_cyclicFormulaCount = 1;

			self::$returnArrayAsType = self::RETURN_ARRAY_AS_ARRAY;
		}

		//	Execute the calculation for the cell formula
		try {
			$result = self::_unwrapResult($this->_calculateFormulaValue($pCell->getValue(), $pCell->getCoordinate(), $pCell));
		} catch (PHPExcel_Exception $e) {
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}

		if ((is_array($result)) && (self::$returnArrayAsType != self::RETURN_ARRAY_AS_ARRAY)) {
			self::$returnArrayAsType = $returnArrayAsType;
			$testResult = PHPExcel_Calculation_Functions::flattenArray($result);
			if (self::$returnArrayAsType == self::RETURN_ARRAY_AS_ERROR) {
				return PHPExcel_Calculation_Functions::VALUE();
			}
			//	If there's only a single cell in the array, then we allow it
			if (count($testResult) != 1) {
				//	If keys are numeric, then it's a matrix result rather than a cell range result, so we permit it
				$r = array_keys($result);
				$r = array_shift($r);
				if (!is_numeric($r)) { return PHPExcel_Calculation_Functions::VALUE(); }
				if (is_array($result[$r])) {
					$c = array_keys($result[$r]);
					$c = array_shift($c);
					if (!is_numeric($c)) {
						return PHPExcel_Calculation_Functions::VALUE();
					}
				}
			}
			$result = array_shift($testResult);
		}
		self::$returnArrayAsType = $returnArrayAsType;


		if ($result === NULL) {
			return 0;
		} elseif((is_float($result)) && ((is_nan($result)) || (is_infinite($result)))) {
			return PHPExcel_Calculation_Functions::NaN();
		}
		return $result;
	}	//	function calculateCellValue(


	/**
	 * Validate and parse a formula string
	 *
	 * @param	string		$formula		Formula to parse
	 * @return	array
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function parseFormula($formula) {
		//	Basic validation that this is indeed a formula
		//	We return an empty array if not
		$formula = trim($formula);
		if ((!isset($formula[0])) || ($formula[0] != '=')) return array();
		$formula = ltrim(substr($formula,1));
		if (!isset($formula[0])) return array();

		//	Parse the formula and return the token stack
		return $this->_parseFormula($formula);
	}	//	function parseFormula()


	/**
	 * Calculate the value of a formula
	 *
	 * @param	string			$formula	Formula to parse
	 * @param	string			$cellID		Address of the cell to calculate
	 * @param	PHPExcel_Cell	$pCell		Cell to calculate
	 * @return	mixed
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function calculateFormula($formula, $cellID=NULL, PHPExcel_Cell $pCell = NULL) {
		//	Initialise the logging settings
		$this->formulaError = null;
		$this->_debugLog->clearLog();
		$this->_cyclicReferenceStack->clear();

		//	Disable calculation cacheing because it only applies to cell calculations, not straight formulae
		//	But don't actually flush any cache
		$resetCache = $this->getCalculationCacheEnabled();
		$this->_calculationCacheEnabled = FALSE;
		//	Execute the calculation
		try {
			$result = self::_unwrapResult($this->_calculateFormulaValue($formula, $cellID, $pCell));
		} catch (PHPExcel_Exception $e) {
			throw new PHPExcel_Calculation_Exception($e->getMessage());
		}

		//	Reset calculation cacheing to its previous state
		$this->_calculationCacheEnabled = $resetCache;

		return $result;
	}	//	function calculateFormula()


    public function getValueFromCache($worksheetName, $cellID, &$cellValue) {
		// Is calculation cacheing enabled?
		// Is the value present in calculation cache?
//echo 'Test cache for ',$worksheetName,'!',$cellID,PHP_EOL;
		$this->_debugLog->writeDebugLog('Testing cache value for cell ', $worksheetName, '!', $cellID);
		if (($this->_calculationCacheEnabled) && (isset($this->_calculationCache[$worksheetName][$cellID]))) {
//echo 'Retrieve from cache',PHP_EOL;
			$this->_debugLog->writeDebugLog('Retrieving value for cell ', $worksheetName, '!', $cellID, ' from cache');
			// Return the cached result
			$cellValue = $this->_calculationCache[$worksheetName][$cellID];
			return TRUE;
		}
		return FALSE;
    }

    public function saveValueToCache($worksheetName, $cellID, $cellValue) {
		if ($this->_calculationCacheEnabled) {
			$this->_calculationCache[$worksheetName][$cellID] = $cellValue;
		}
	}

	/**
	 * Parse a cell formula and calculate its value
	 *
	 * @param	string			$formula	The formula to parse and calculate
	 * @param	string			$cellID		The ID (e.g. A3) of the cell that we are calculating
	 * @param	PHPExcel_Cell	$pCell		Cell to calculate
	 * @return	mixed
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function _calculateFormulaValue($formula, $cellID=null, PHPExcel_Cell $pCell = null) {
		$cellValue = '';

		//	Basic validation that this is indeed a formula
		//	We simply return the cell value if not
		$formula = trim($formula);
		if ($formula[0] != '=') return self::_wrapResult($formula);
		$formula = ltrim(substr($formula,1));
		if (!isset($formula[0])) return self::_wrapResult($formula);

		$pCellParent = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;
		$wsTitle = ($pCellParent !== NULL) ? $pCellParent->getTitle() : "\x00Wrk";

		if (($cellID !== NULL) && ($this->getValueFromCache($wsTitle, $cellID, $cellValue))) {
			return $cellValue;
		}

		if (($wsTitle[0] !== "\x00") && ($this->_cyclicReferenceStack->onStack($wsTitle.'!'.$cellID))) {
			if ($this->cyclicFormulaCount <= 0) {
				return $this->_raiseFormulaError('Cyclic Reference in Formula');
			} elseif (($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) &&
					  ($this->_cyclicFormulaCell == $wsTitle.'!'.$cellID)) {
				return $cellValue;
			} elseif ($this->_cyclicFormulaCell == $wsTitle.'!'.$cellID) {
				++$this->_cyclicFormulaCount;
				if ($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) {
					return $cellValue;
				}
			} elseif ($this->_cyclicFormulaCell == '') {
				$this->_cyclicFormulaCell = $wsTitle.'!'.$cellID;
				if ($this->_cyclicFormulaCount >= $this->cyclicFormulaCount) {
					return $cellValue;
				}
			}
		}

		//	Parse the formula onto the token stack and calculate the value
		$this->_cyclicReferenceStack->push($wsTitle.'!'.$cellID);
		$cellValue = $this->_processTokenStack($this->_parseFormula($formula, $pCell), $cellID, $pCell);
		$this->_cyclicReferenceStack->pop();

		// Save to calculation cache
		if ($cellID !== NULL) {
			$this->saveValueToCache($wsTitle, $cellID, $cellValue);
		}

		//	Return the calculated value
		return $cellValue;
	}	//	function _calculateFormulaValue()


	/**
	 * Ensure that paired matrix operands are both matrices and of the same size
	 *
	 * @param	mixed		&$operand1	First matrix operand
	 * @param	mixed		&$operand2	Second matrix operand
	 * @param	integer		$resize		Flag indicating whether the matrices should be resized to match
	 *										and (if so), whether the smaller dimension should grow or the
	 *										larger should shrink.
	 *											0 = no resize
	 *											1 = shrink to fit
	 *											2 = extend to fit
	 */
	private static function _checkMatrixOperands(&$operand1,&$operand2,$resize = 1) {
		//	Examine each of the two operands, and turn them into an array if they aren't one already
		//	Note that this function should only be called if one or both of the operand is already an array
		if (!is_array($operand1)) {
			list($matrixRows,$matrixColumns) = self::_getMatrixDimensions($operand2);
			$operand1 = array_fill(0,$matrixRows,array_fill(0,$matrixColumns,$operand1));
			$resize = 0;
		} elseif (!is_array($operand2)) {
			list($matrixRows,$matrixColumns) = self::_getMatrixDimensions($operand1);
			$operand2 = array_fill(0,$matrixRows,array_fill(0,$matrixColumns,$operand2));
			$resize = 0;
		}

		list($matrix1Rows,$matrix1Columns) = self::_getMatrixDimensions($operand1);
		list($matrix2Rows,$matrix2Columns) = self::_getMatrixDimensions($operand2);
		if (($matrix1Rows == $matrix2Columns) && ($matrix2Rows == $matrix1Columns)) {
			$resize = 1;
		}

		if ($resize == 2) {
			//	Given two matrices of (potentially) unequal size, convert the smaller in each dimension to match the larger
			self::_resizeMatricesExtend($operand1,$operand2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
		} elseif ($resize == 1) {
			//	Given two matrices of (potentially) unequal size, convert the larger in each dimension to match the smaller
			self::_resizeMatricesShrink($operand1,$operand2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
		}
		return array( $matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns);
	}	//	function _checkMatrixOperands()


	/**
	 * Read the dimensions of a matrix, and re-index it with straight numeric keys starting from row 0, column 0
	 *
	 * @param	mixed		&$matrix		matrix operand
	 * @return	array		An array comprising the number of rows, and number of columns
	 */
	public static function _getMatrixDimensions(&$matrix) {
		$matrixRows = count($matrix);
		$matrixColumns = 0;
		foreach($matrix as $rowKey => $rowValue) {
			$matrixColumns = max(count($rowValue),$matrixColumns);
			if (!is_array($rowValue)) {
				$matrix[$rowKey] = array($rowValue);
			} else {
				$matrix[$rowKey] = array_values($rowValue);
			}
		}
		$matrix = array_values($matrix);
		return array($matrixRows,$matrixColumns);
	}	//	function _getMatrixDimensions()


	/**
	 * Ensure that paired matrix operands are both matrices of the same size
	 *
	 * @param	mixed		&$matrix1		First matrix operand
	 * @param	mixed		&$matrix2		Second matrix operand
	 * @param	integer		$matrix1Rows	Row size of first matrix operand
	 * @param	integer		$matrix1Columns	Column size of first matrix operand
	 * @param	integer		$matrix2Rows	Row size of second matrix operand
	 * @param	integer		$matrix2Columns	Column size of second matrix operand
	 */
	private static function _resizeMatricesShrink(&$matrix1,&$matrix2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns) {
		if (($matrix2Columns < $matrix1Columns) || ($matrix2Rows < $matrix1Rows)) {
			if ($matrix2Rows < $matrix1Rows) {
				for ($i = $matrix2Rows; $i < $matrix1Rows; ++$i) {
					unset($matrix1[$i]);
				}
			}
			if ($matrix2Columns < $matrix1Columns) {
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
						unset($matrix1[$i][$j]);
					}
				}
			}
		}

		if (($matrix1Columns < $matrix2Columns) || ($matrix1Rows < $matrix2Rows)) {
			if ($matrix1Rows < $matrix2Rows) {
				for ($i = $matrix1Rows; $i < $matrix2Rows; ++$i) {
					unset($matrix2[$i]);
				}
			}
			if ($matrix1Columns < $matrix2Columns) {
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
						unset($matrix2[$i][$j]);
					}
				}
			}
		}
	}	//	function _resizeMatricesShrink()


	/**
	 * Ensure that paired matrix operands are both matrices of the same size
	 *
	 * @param	mixed		&$matrix1	First matrix operand
	 * @param	mixed		&$matrix2	Second matrix operand
	 * @param	integer		$matrix1Rows	Row size of first matrix operand
	 * @param	integer		$matrix1Columns	Column size of first matrix operand
	 * @param	integer		$matrix2Rows	Row size of second matrix operand
	 * @param	integer		$matrix2Columns	Column size of second matrix operand
	 */
	private static function _resizeMatricesExtend(&$matrix1,&$matrix2,$matrix1Rows,$matrix1Columns,$matrix2Rows,$matrix2Columns) {
		if (($matrix2Columns < $matrix1Columns) || ($matrix2Rows < $matrix1Rows)) {
			if ($matrix2Columns < $matrix1Columns) {
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					$x = $matrix2[$i][$matrix2Columns-1];
					for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
						$matrix2[$i][$j] = $x;
					}
				}
			}
			if ($matrix2Rows < $matrix1Rows) {
				$x = $matrix2[$matrix2Rows-1];
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					$matrix2[$i] = $x;
				}
			}
		}

		if (($matrix1Columns < $matrix2Columns) || ($matrix1Rows < $matrix2Rows)) {
			if ($matrix1Columns < $matrix2Columns) {
				for ($i = 0; $i < $matrix1Rows; ++$i) {
					$x = $matrix1[$i][$matrix1Columns-1];
					for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
						$matrix1[$i][$j] = $x;
					}
				}
			}
			if ($matrix1Rows < $matrix2Rows) {
				$x = $matrix1[$matrix1Rows-1];
				for ($i = 0; $i < $matrix2Rows; ++$i) {
					$matrix1[$i] = $x;
				}
			}
		}
	}	//	function _resizeMatricesExtend()


	/**
	 * Format details of an operand for display in the log (based on operand type)
	 *
	 * @param	mixed		$value	First matrix operand
	 * @return	mixed
	 */
	private function _showValue($value) {
		if ($this->_debugLog->getWriteDebugLog()) {
			$testArray = PHPExcel_Calculation_Functions::flattenArray($value);
			if (count($testArray) == 1) {
				$value = array_pop($testArray);
			}

			if (is_array($value)) {
				$returnMatrix = array();
				$pad = $rpad = ', ';
				foreach($value as $row) {
					if (is_array($row)) {
						$returnMatrix[] = implode($pad,array_map(array($this,'_showValue'),$row));
						$rpad = '; ';
					} else {
						$returnMatrix[] = $this->_showValue($row);
					}
				}
				return '{ '.implode($rpad,$returnMatrix).' }';
			} elseif(is_string($value) && (trim($value,'"') == $value)) {
				return '"'.$value.'"';
			} elseif(is_bool($value)) {
				return ($value) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
			}
		}
		return PHPExcel_Calculation_Functions::flattenSingleValue($value);
	}	//	function _showValue()


	/**
	 * Format type and details of an operand for display in the log (based on operand type)
	 *
	 * @param	mixed		$value	First matrix operand
	 * @return	mixed
	 */
	private function _showTypeDetails($value) {
		if ($this->_debugLog->getWriteDebugLog()) {
			$testArray = PHPExcel_Calculation_Functions::flattenArray($value);
			if (count($testArray) == 1) {
				$value = array_pop($testArray);
			}

			if ($value === NULL) {
				return 'a NULL value';
			} elseif (is_float($value)) {
				$typeString = 'a floating point number';
			} elseif(is_int($value)) {
				$typeString = 'an integer number';
			} elseif(is_bool($value)) {
				$typeString = 'a boolean';
			} elseif(is_array($value)) {
				$typeString = 'a matrix';
			} else {
				if ($value == '') {
					return 'an empty string';
				} elseif ($value[0] == '#') {
					return 'a '.$value.' error';
				} else {
					$typeString = 'a string';
				}
			}
			return $typeString.' with a value of '.$this->_showValue($value);
		}
	}	//	function _showTypeDetails()


	private static function _convertMatrixReferences($formula) {
		static $matrixReplaceFrom = array('{',';','}');
		static $matrixReplaceTo = array('MKMATRIX(MKMATRIX(','),MKMATRIX(','))');

		//	Convert any Excel matrix references to the MKMATRIX() function
		if (strpos($formula,'{') !== FALSE) {
			//	If there is the possibility of braces within a quoted string, then we don't treat those as matrix indicators
			if (strpos($formula,'"') !== FALSE) {
				//	So instead we skip replacing in any quoted strings by only replacing in every other array element after we've exploded
				//		the formula
				$temp = explode('"',$formula);
				//	Open and Closed counts used for trapping mismatched braces in the formula
				$openCount = $closeCount = 0;
				$i = FALSE;
				foreach($temp as &$value) {
					//	Only count/replace in alternating array entries
					if ($i = !$i) {
						$openCount += substr_count($value,'{');
						$closeCount += substr_count($value,'}');
						$value = str_replace($matrixReplaceFrom,$matrixReplaceTo,$value);
					}
				}
				unset($value);
				//	Then rebuild the formula string
				$formula = implode('"',$temp);
			} else {
				//	If there's no quoted strings, then we do a simple count/replace
				$openCount = substr_count($formula,'{');
				$closeCount = substr_count($formula,'}');
				$formula = str_replace($matrixReplaceFrom,$matrixReplaceTo,$formula);
			}
			//	Trap for mismatched braces and trigger an appropriate error
			if ($openCount < $closeCount) {
				if ($openCount > 0) {
					return $this->_raiseFormulaError("Formula Error: Mismatched matrix braces '}'");
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected '}' encountered");
				}
			} elseif ($openCount > $closeCount) {
				if ($closeCount > 0) {
					return $this->_raiseFormulaError("Formula Error: Mismatched matrix braces '{'");
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected '{' encountered");
				}
			}
		}

		return $formula;
	}	//	function _convertMatrixReferences()


	private static function _mkMatrix() {
		return func_get_args();
	}	//	function _mkMatrix()


	//	Binary Operators
	//	These operators always work on two values
	//	Array key is the operator, the value indicates whether this is a left or right associative operator
	private static $_operatorAssociativity	= array(
		'^' => 0,															//	Exponentiation
		'*' => 0, '/' => 0, 												//	Multiplication and Division
		'+' => 0, '-' => 0,													//	Addition and Subtraction
		'&' => 0,															//	Concatenation
		'|' => 0, ':' => 0,													//	Intersect and Range
		'>' => 0, '<' => 0, '=' => 0, '>=' => 0, '<=' => 0, '<>' => 0		//	Comparison
	);

	//	Comparison (Boolean) Operators
	//	These operators work on two values, but always return a boolean result
	private static $_comparisonOperators	= array('>' => TRUE, '<' => TRUE, '=' => TRUE, '>=' => TRUE, '<=' => TRUE, '<>' => TRUE);

	//	Operator Precedence
	//	This list includes all valid operators, whether binary (including boolean) or unary (such as %)
	//	Array key is the operator, the value is its precedence
	private static $_operatorPrecedence	= array(
		':' => 8,																//	Range
		'|' => 7,																//	Intersect
		'~' => 6,																//	Negation
		'%' => 5,																//	Percentage
		'^' => 4,																//	Exponentiation
		'*' => 3, '/' => 3, 													//	Multiplication and Division
		'+' => 2, '-' => 2,														//	Addition and Subtraction
		'&' => 1,																//	Concatenation
		'>' => 0, '<' => 0, '=' => 0, '>=' => 0, '<=' => 0, '<>' => 0			//	Comparison
	);

	// Convert infix to postfix notation
	private function _parseFormula($formula, PHPExcel_Cell $pCell = NULL) {
		if (($formula = self::_convertMatrixReferences(trim($formula))) === FALSE) {
			return FALSE;
		}

		//	If we're using cell caching, then $pCell may well be flushed back to the cache (which detaches the parent worksheet),
		//		so we store the parent worksheet so that we can re-attach it when necessary
		$pCellParent = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;

		$regexpMatchString = '/^('.self::CALCULATION_REGEXP_FUNCTION.
							   '|'.self::CALCULATION_REGEXP_CELLREF.
							   '|'.self::CALCULATION_REGEXP_NUMBER.
							   '|'.self::CALCULATION_REGEXP_STRING.
							   '|'.self::CALCULATION_REGEXP_OPENBRACE.
							   '|'.self::CALCULATION_REGEXP_NAMEDRANGE.
							   '|'.self::CALCULATION_REGEXP_ERROR.
							 ')/si';

		//	Start with initialisation
		$index = 0;
		$stack = new PHPExcel_Calculation_Token_Stack;
		$output = array();
		$expectingOperator = FALSE;					//	We use this test in syntax-checking the expression to determine when a
													//		- is a negation or + is a positive operator rather than an operation
		$expectingOperand = FALSE;					//	We use this test in syntax-checking the expression to determine whether an operand
													//		should be null in a function call
		//	The guts of the lexical parser
		//	Loop through the formula extracting each operator and operand in turn
		while(TRUE) {
//echo 'Assessing Expression '.substr($formula, $index),PHP_EOL;
			$opCharacter = $formula[$index];	//	Get the first character of the value at the current index position
//echo 'Initial character of expression block is '.$opCharacter,PHP_EOL;
			if ((isset(self::$_comparisonOperators[$opCharacter])) && (strlen($formula) > $index) && (isset(self::$_comparisonOperators[$formula[$index+1]]))) {
				$opCharacter .= $formula[++$index];
//echo 'Initial character of expression block is comparison operator '.$opCharacter.PHP_EOL;
			}

			//	Find out if we're currently at the beginning of a number, variable, cell reference, function, parenthesis or operand
			$isOperandOrFunction = preg_match($regexpMatchString, substr($formula, $index), $match);
//echo '$isOperandOrFunction is '.(($isOperandOrFunction) ? 'True' : 'False').PHP_EOL;
//var_dump($match);

			if ($opCharacter == '-' && !$expectingOperator) {				//	Is it a negation instead of a minus?
//echo 'Element is a Negation operator',PHP_EOL;
				$stack->push('Unary Operator','~');							//	Put a negation on the stack
				++$index;													//		and drop the negation symbol
			} elseif ($opCharacter == '%' && $expectingOperator) {
//echo 'Element is a Percentage operator',PHP_EOL;
				$stack->push('Unary Operator','%');							//	Put a percentage on the stack
				++$index;
			} elseif ($opCharacter == '+' && !$expectingOperator) {			//	Positive (unary plus rather than binary operator plus) can be discarded?
//echo 'Element is a Positive number, not Plus operator',PHP_EOL;
				++$index;													//	Drop the redundant plus symbol
			} elseif ((($opCharacter == '~') || ($opCharacter == '|')) && (!$isOperandOrFunction)) {	//	We have to explicitly deny a tilde or pipe, because they are legal
				return $this->_raiseFormulaError("Formula Error: Illegal character '~'");				//		on the stack but not in the input expression

			} elseif ((isset(self::$_operators[$opCharacter]) or $isOperandOrFunction) && $expectingOperator) {	//	Are we putting an operator on the stack?
//echo 'Element with value '.$opCharacter.' is an Operator',PHP_EOL;
				while($stack->count() > 0 &&
					($o2 = $stack->last()) &&
					isset(self::$_operators[$o2['value']]) &&
					@(self::$_operatorAssociativity[$opCharacter] ? self::$_operatorPrecedence[$opCharacter] < self::$_operatorPrecedence[$o2['value']] : self::$_operatorPrecedence[$opCharacter] <= self::$_operatorPrecedence[$o2['value']])) {
					$output[] = $stack->pop();								//	Swap operands and higher precedence operators from the stack to the output
				}
				$stack->push('Binary Operator',$opCharacter);	//	Finally put our current operator onto the stack
				++$index;
				$expectingOperator = FALSE;

			} elseif ($opCharacter == ')' && $expectingOperator) {			//	Are we expecting to close a parenthesis?
//echo 'Element is a Closing bracket',PHP_EOL;
				$expectingOperand = FALSE;
				while (($o2 = $stack->pop()) && $o2['value'] != '(') {		//	Pop off the stack back to the last (
					if ($o2 === NULL) return $this->_raiseFormulaError('Formula Error: Unexpected closing brace ")"');
					else $output[] = $o2;
				}
				$d = $stack->last(2);
				if (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $d['value'], $matches)) {	//	Did this parenthesis just close a function?
					$functionName = $matches[1];										//	Get the function name
//echo 'Closed Function is '.$functionName,PHP_EOL;
					$d = $stack->pop();
					$argumentCount = $d['value'];		//	See how many arguments there were (argument count is the next value stored on the stack)
//if ($argumentCount == 0) {
//	echo 'With no arguments',PHP_EOL;
//} elseif ($argumentCount == 1) {
//	echo 'With 1 argument',PHP_EOL;
//} else {
//	echo 'With '.$argumentCount.' arguments',PHP_EOL;
//}
					$output[] = $d;						//	Dump the argument count on the output
					$output[] = $stack->pop();			//	Pop the function and push onto the output
					if (isset(self::$_controlFunctions[$functionName])) {
//echo 'Built-in function '.$functionName,PHP_EOL;
						$expectedArgumentCount = self::$_controlFunctions[$functionName]['argumentCount'];
						$functionCall = self::$_controlFunctions[$functionName]['functionCall'];
					} elseif (isset(self::$_PHPExcelFunctions[$functionName])) {
//echo 'PHPExcel function '.$functionName,PHP_EOL;
						$expectedArgumentCount = self::$_PHPExcelFunctions[$functionName]['argumentCount'];
						$functionCall = self::$_PHPExcelFunctions[$functionName]['functionCall'];
					} else {	// did we somehow push a non-function on the stack? this should never happen
						return $this->_raiseFormulaError("Formula Error: Internal error, non-function on stack");
					}
					//	Check the argument count
					$argumentCountError = FALSE;
					if (is_numeric($expectedArgumentCount)) {
						if ($expectedArgumentCount < 0) {
//echo '$expectedArgumentCount is between 0 and '.abs($expectedArgumentCount),PHP_EOL;
							if ($argumentCount > abs($expectedArgumentCount)) {
								$argumentCountError = TRUE;
								$expectedArgumentCountString = 'no more than '.abs($expectedArgumentCount);
							}
						} else {
//echo '$expectedArgumentCount is numeric '.$expectedArgumentCount,PHP_EOL;
							if ($argumentCount != $expectedArgumentCount) {
								$argumentCountError = TRUE;
								$expectedArgumentCountString = $expectedArgumentCount;
							}
						}
					} elseif ($expectedArgumentCount != '*') {
						$isOperandOrFunction = preg_match('/(\d*)([-+,])(\d*)/',$expectedArgumentCount,$argMatch);
//print_r($argMatch);
//echo PHP_EOL;
						switch ($argMatch[2]) {
							case '+' :
								if ($argumentCount < $argMatch[1]) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = $argMatch[1].' or more ';
								}
								break;
							case '-' :
								if (($argumentCount < $argMatch[1]) || ($argumentCount > $argMatch[3])) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = 'between '.$argMatch[1].' and '.$argMatch[3];
								}
								break;
							case ',' :
								if (($argumentCount != $argMatch[1]) && ($argumentCount != $argMatch[3])) {
									$argumentCountError = TRUE;
									$expectedArgumentCountString = 'either '.$argMatch[1].' or '.$argMatch[3];
								}
								break;
						}
					}
					if ($argumentCountError) {
						return $this->_raiseFormulaError("Formula Error: Wrong number of arguments for $functionName() function: $argumentCount given, ".$expectedArgumentCountString." expected");
					}
				}
				++$index;

			} elseif ($opCharacter == ',') {			//	Is this the separator for function arguments?
//echo 'Element is a Function argument separator',PHP_EOL;
				while (($o2 = $stack->pop()) && $o2['value'] != '(') {		//	Pop off the stack back to the last (
					if ($o2 === NULL) return $this->_raiseFormulaError("Formula Error: Unexpected ,");
					else $output[] = $o2;	// pop the argument expression stuff and push onto the output
				}
				//	If we've a comma when we're expecting an operand, then what we actually have is a null operand;
				//		so push a null onto the stack
				if (($expectingOperand) || (!$expectingOperator)) {
					$output[] = array('type' => 'NULL Value', 'value' => self::$_ExcelConstants['NULL'], 'reference' => NULL);
				}
				// make sure there was a function
				$d = $stack->last(2);
				if (!preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $d['value'], $matches))
					return $this->_raiseFormulaError("Formula Error: Unexpected ,");
				$d = $stack->pop();
				$stack->push($d['type'],++$d['value'],$d['reference']);	// increment the argument count
				$stack->push('Brace', '(');	// put the ( back on, we'll need to pop back to it again
				$expectingOperator = FALSE;
				$expectingOperand = TRUE;
				++$index;

			} elseif ($opCharacter == '(' && !$expectingOperator) {
//				echo 'Element is an Opening Bracket<br />';
				$stack->push('Brace', '(');
				++$index;

			} elseif ($isOperandOrFunction && !$expectingOperator) {	// do we now have a function/variable/number?
				$expectingOperator = TRUE;
				$expectingOperand = FALSE;
				$val = $match[1];
				$length = strlen($val);
//				echo 'Element with value '.$val.' is an Operand, Variable, Constant, String, Number, Cell Reference or Function<br />';

				if (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $val, $matches)) {
					$val = preg_replace('/\s/','',$val);
//					echo 'Element '.$val.' is a Function<br />';
					if (isset(self::$_PHPExcelFunctions[strtoupper($matches[1])]) || isset(self::$_controlFunctions[strtoupper($matches[1])])) {	// it's a function
						$stack->push('Function', strtoupper($val));
						$ax = preg_match('/^\s*(\s*\))/i', substr($formula, $index+$length), $amatch);
						if ($ax) {
							$stack->push('Operand Count for Function '.strtoupper($val).')', 0);
							$expectingOperator = TRUE;
						} else {
							$stack->push('Operand Count for Function '.strtoupper($val).')', 1);
							$expectingOperator = FALSE;
						}
						$stack->push('Brace', '(');
					} else {	// it's a var w/ implicit multiplication
						$output[] = array('type' => 'Value', 'value' => $matches[1], 'reference' => NULL);
					}
				} elseif (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $val, $matches)) {
//					echo 'Element '.$val.' is a Cell reference<br />';
					//	Watch for this case-change when modifying to allow cell references in different worksheets...
					//	Should only be applied to the actual cell column, not the worksheet name

					//	If the last entry on the stack was a : operator, then we have a cell range reference
					$testPrevOp = $stack->last(1);
					if ($testPrevOp['value'] == ':') {
						//	If we have a worksheet reference, then we're playing with a 3D reference
						if ($matches[2] == '') {
							//	Otherwise, we 'inherit' the worksheet reference from the start cell reference
							//	The start of the cell range reference should be the last entry in $output
							$startCellRef = $output[count($output)-1]['value'];
							preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $startCellRef, $startMatches);
							if ($startMatches[2] > '') {
								$val = $startMatches[2].'!'.$val;
							}
						} else {
							return $this->_raiseFormulaError("3D Range references are not yet supported");
						}
					}

					$output[] = array('type' => 'Cell Reference', 'value' => $val, 'reference' => $val);
//					$expectingOperator = FALSE;
				} else {	// it's a variable, constant, string, number or boolean
//					echo 'Element is a Variable, Constant, String, Number or Boolean<br />';
					//	If the last entry on the stack was a : operator, then we may have a row or column range reference
					$testPrevOp = $stack->last(1);
					if ($testPrevOp['value'] == ':') {
						$startRowColRef = $output[count($output)-1]['value'];
						$rangeWS1 = '';
						if (strpos('!',$startRowColRef) !== FALSE) {
							list($rangeWS1,$startRowColRef) = explode('!',$startRowColRef);
						}
						if ($rangeWS1 != '') $rangeWS1 .= '!';
						$rangeWS2 = $rangeWS1;
						if (strpos('!',$val) !== FALSE) {
							list($rangeWS2,$val) = explode('!',$val);
						}
						if ($rangeWS2 != '') $rangeWS2 .= '!';
						if ((is_integer($startRowColRef)) && (ctype_digit($val)) &&
							($startRowColRef <= 1048576) && ($val <= 1048576)) {
							//	Row range
							$endRowColRef = ($pCellParent !== NULL) ? $pCellParent->getHighestColumn() : 'XFD';	//	Max 16,384 columns for Excel2007
							$output[count($output)-1]['value'] = $rangeWS1.'A'.$startRowColRef;
							$val = $rangeWS2.$endRowColRef.$val;
						} elseif ((ctype_alpha($startRowColRef)) && (ctype_alpha($val)) &&
							(strlen($startRowColRef) <= 3) && (strlen($val) <= 3)) {
							//	Column range
							$endRowColRef = ($pCellParent !== NULL) ? $pCellParent->getHighestRow() : 1048576;		//	Max 1,048,576 rows for Excel2007
							$output[count($output)-1]['value'] = $rangeWS1.strtoupper($startRowColRef).'1';
							$val = $rangeWS2.$val.$endRowColRef;
						}
					}

					$localeConstant = FALSE;
					if ($opCharacter == '"') {
//						echo 'Element is a String<br />';
						//	UnEscape any quotes within the string
						$val = self::_wrapResult(str_replace('""','"',self::_unwrapResult($val)));
					} elseif (is_numeric($val)) {
//						echo 'Element is a Number<br />';
						if ((strpos($val,'.') !== FALSE) || (stripos($val,'e') !== FALSE) || ($val > PHP_INT_MAX) || ($val < -PHP_INT_MAX)) {
//							echo 'Casting '.$val.' to float<br />';
							$val = (float) $val;
						} else {
//							echo 'Casting '.$val.' to integer<br />';
							$val = (integer) $val;
						}
					} elseif (isset(self::$_ExcelConstants[trim(strtoupper($val))])) {
						$excelConstant = trim(strtoupper($val));
//						echo 'Element '.$excelConstant.' is an Excel Constant<br />';
						$val = self::$_ExcelConstants[$excelConstant];
					} elseif (($localeConstant = array_search(trim(strtoupper($val)), self::$_localeBoolean)) !== FALSE) {
//						echo 'Element '.$localeConstant.' is an Excel Constant<br />';
						$val = self::$_ExcelConstants[$localeConstant];
					}
					$details = array('type' => 'Value', 'value' => $val, 'reference' => NULL);
					if ($localeConstant) { $details['localeValue'] = $localeConstant; }
					$output[] = $details;
				}
				$index += $length;

			} elseif ($opCharacter == '$') {	// absolute row or column range
				++$index;
			} elseif ($opCharacter == ')') {	// miscellaneous error checking
				if ($expectingOperand) {
					$output[] = array('type' => 'NULL Value', 'value' => self::$_ExcelConstants['NULL'], 'reference' => NULL);
					$expectingOperand = FALSE;
					$expectingOperator = TRUE;
				} else {
					return $this->_raiseFormulaError("Formula Error: Unexpected ')'");
				}
			} elseif (isset(self::$_operators[$opCharacter]) && !$expectingOperator) {
				return $this->_raiseFormulaError("Formula Error: Unexpected operator '$opCharacter'");
			} else {	// I don't even want to know what you did to get here
				return $this->_raiseFormulaError("Formula Error: An unexpected error occured");
			}
			//	Test for end of formula string
			if ($index == strlen($formula)) {
				//	Did we end with an operator?.
				//	Only valid for the % unary operator
				if ((isset(self::$_operators[$opCharacter])) && ($opCharacter != '%')) {
					return $this->_raiseFormulaError("Formula Error: Operator '$opCharacter' has no operands");
				} else {
					break;
				}
			}
			//	Ignore white space
			while (($formula[$index] == "\n") || ($formula[$index] == "\r")) {
				++$index;
			}
			if ($formula[$index] == ' ') {
				while ($formula[$index] == ' ') {
					++$index;
				}
				//	If we're expecting an operator, but only have a space between the previous and next operands (and both are
				//		Cell References) then we have an INTERSECTION operator
//				echo 'Possible Intersect Operator<br />';
				if (($expectingOperator) && (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'.*/Ui', substr($formula, $index), $match)) &&
					($output[count($output)-1]['type'] == 'Cell Reference')) {
//					echo 'Element is an Intersect Operator<br />';
					while($stack->count() > 0 &&
						($o2 = $stack->last()) &&
						isset(self::$_operators[$o2['value']]) &&
						@(self::$_operatorAssociativity[$opCharacter] ? self::$_operatorPrecedence[$opCharacter] < self::$_operatorPrecedence[$o2['value']] : self::$_operatorPrecedence[$opCharacter] <= self::$_operatorPrecedence[$o2['value']])) {
						$output[] = $stack->pop();								//	Swap operands and higher precedence operators from the stack to the output
					}
					$stack->push('Binary Operator','|');	//	Put an Intersect Operator on the stack
					$expectingOperator = FALSE;
				}
			}
		}

		while (($op = $stack->pop()) !== NULL) {	// pop everything off the stack and push onto output
			if ((is_array($op) && $op['value'] == '(') || ($op === '('))
				return $this->_raiseFormulaError("Formula Error: Expecting ')'");	// if there are any opening braces on the stack, then braces were unbalanced
			$output[] = $op;
		}
		return $output;
	}	//	function _parseFormula()


	private static function _dataTestReference(&$operandData)
	{
		$operand = $operandData['value'];
		if (($operandData['reference'] === NULL) && (is_array($operand))) {
			$rKeys = array_keys($operand);
			$rowKey = array_shift($rKeys);
			$cKeys = array_keys(array_keys($operand[$rowKey]));
			$colKey = array_shift($cKeys);
			if (ctype_upper($colKey)) {
				$operandData['reference'] = $colKey.$rowKey;
			}
		}
		return $operand;
	}

	// evaluate postfix notation
	private function _processTokenStack($tokens, $cellID = NULL, PHPExcel_Cell $pCell = NULL) {
		if ($tokens == FALSE) return FALSE;

		//	If we're using cell caching, then $pCell may well be flushed back to the cache (which detaches the parent cell collection),
		//		so we store the parent cell collection so that we can re-attach it when necessary
		$pCellWorksheet = ($pCell !== NULL) ? $pCell->getWorksheet() : NULL;
		$pCellParent = ($pCell !== NULL) ? $pCell->getParent() : null;
		$stack = new PHPExcel_Calculation_Token_Stack;

		//	Loop through each token in turn
		foreach ($tokens as $tokenData) {
//			print_r($tokenData);
//			echo '<br />';
			$token = $tokenData['value'];
//			echo '<b>Token is '.$token.'</b><br />';
			// if the token is a binary operator, pop the top two values off the stack, do the operation, and push the result back on the stack
			if (isset(self::$_binaryOperators[$token])) {
//				echo 'Token is a binary operator<br />';
				//	We must have two operands, error if we don't
				if (($operand2Data = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');
				if (($operand1Data = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');

				$operand1 = self::_dataTestReference($operand1Data);
				$operand2 = self::_dataTestReference($operand2Data);

				//	Log what we're doing
				if ($token == ':') {
					$this->_debugLog->writeDebugLog('Evaluating Range ', $this->_showValue($operand1Data['reference']), ' ', $token, ' ', $this->_showValue($operand2Data['reference']));
				} else {
					$this->_debugLog->writeDebugLog('Evaluating ', $this->_showValue($operand1), ' ', $token, ' ', $this->_showValue($operand2));
				}

				//	Process the operation in the appropriate manner
				switch ($token) {
					//	Comparison (Boolean) Operators
					case '>'	:			//	Greater than
					case '<'	:			//	Less than
					case '>='	:			//	Greater than or Equal to
					case '<='	:			//	Less than or Equal to
					case '='	:			//	Equality
					case '<>'	:			//	Inequality
						$this->_executeBinaryComparisonOperation($cellID,$operand1,$operand2,$token,$stack);
						break;
					//	Binary Operators
					case ':'	:			//	Range
						$sheet1 = $sheet2 = '';
						if (strpos($operand1Data['reference'],'!') !== FALSE) {
							list($sheet1,$operand1Data['reference']) = explode('!',$operand1Data['reference']);
						} else {
							$sheet1 = ($pCellParent !== NULL) ? $pCellWorksheet->getTitle() : '';
						}
						if (strpos($operand2Data['reference'],'!') !== FALSE) {
							list($sheet2,$operand2Data['reference']) = explode('!',$operand2Data['reference']);
						} else {
							$sheet2 = $sheet1;
						}
						if ($sheet1 == $sheet2) {
							if ($operand1Data['reference'] === NULL) {
								if ((trim($operand1Data['value']) != '') && (is_numeric($operand1Data['value']))) {
									$operand1Data['reference'] = $pCell->getColumn().$operand1Data['value'];
								} elseif (trim($operand1Data['reference']) == '') {
									$operand1Data['reference'] = $pCell->getCoordinate();
								} else {
									$operand1Data['reference'] = $operand1Data['value'].$pCell->getRow();
								}
							}
							if ($operand2Data['reference'] === NULL) {
								if ((trim($operand2Data['value']) != '') && (is_numeric($operand2Data['value']))) {
									$operand2Data['reference'] = $pCell->getColumn().$operand2Data['value'];
								} elseif (trim($operand2Data['reference']) == '') {
									$operand2Data['reference'] = $pCell->getCoordinate();
								} else {
									$operand2Data['reference'] = $operand2Data['value'].$pCell->getRow();
								}
							}

							$oData = array_merge(explode(':',$operand1Data['reference']),explode(':',$operand2Data['reference']));
							$oCol = $oRow = array();
							foreach($oData as $oDatum) {
								$oCR = PHPExcel_Cell::coordinateFromString($oDatum);
								$oCol[] = PHPExcel_Cell::columnIndexFromString($oCR[0]) - 1;
								$oRow[] = $oCR[1];
							}
							$cellRef = PHPExcel_Cell::stringFromColumnIndex(min($oCol)).min($oRow).':'.PHPExcel_Cell::stringFromColumnIndex(max($oCol)).max($oRow);
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($sheet1), FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$stack->push('Cell Reference',$cellValue,$cellRef);
						} else {
							$stack->push('Error',PHPExcel_Calculation_Functions::REF(),NULL);
						}

						break;
					case '+'	:			//	Addition
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'plusEquals',$stack);
						break;
					case '-'	:			//	Subtraction
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'minusEquals',$stack);
						break;
					case '*'	:			//	Multiplication
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'arrayTimesEquals',$stack);
						break;
					case '/'	:			//	Division
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'arrayRightDivide',$stack);
						break;
					case '^'	:			//	Exponential
						$this->_executeNumericBinaryOperation($cellID,$operand1,$operand2,$token,'power',$stack);
						break;
					case '&'	:			//	Concatenation
						//	If either of the operands is a matrix, we need to treat them both as matrices
						//		(converting the other operand to a matrix if need be); then perform the required
						//		matrix operation
						if (is_bool($operand1)) {
							$operand1 = ($operand1) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
						}
						if (is_bool($operand2)) {
							$operand2 = ($operand2) ? self::$_localeBoolean['TRUE'] : self::$_localeBoolean['FALSE'];
						}
						if ((is_array($operand1)) || (is_array($operand2))) {
							//	Ensure that both operands are arrays/matrices
							self::_checkMatrixOperands($operand1,$operand2,2);
							try {
								//	Convert operand 1 from a PHP array to a matrix
								$matrix = new PHPExcel_Shared_JAMA_Matrix($operand1);
								//	Perform the required operation against the operand 1 matrix, passing in operand 2
								$matrixResult = $matrix->concat($operand2);
								$result = $matrixResult->getArray();
							} catch (PHPExcel_Exception $ex) {
								$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
								$result = '#VALUE!';
							}
						} else {
							$result = '"'.str_replace('""','"',self::_unwrapResult($operand1,'"').self::_unwrapResult($operand2,'"')).'"';
						}
						$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
						$stack->push('Value',$result);
						break;
					case '|'	:			//	Intersect
						$rowIntersect = array_intersect_key($operand1,$operand2);
						$cellIntersect = $oCol = $oRow = array();
						foreach(array_keys($rowIntersect) as $row) {
							$oRow[] = $row;
							foreach($rowIntersect[$row] as $col => $data) {
								$oCol[] = PHPExcel_Cell::columnIndexFromString($col) - 1;
								$cellIntersect[$row] = array_intersect_key($operand1[$row],$operand2[$row]);
							}
						}
						$cellRef = PHPExcel_Cell::stringFromColumnIndex(min($oCol)).min($oRow).':'.PHPExcel_Cell::stringFromColumnIndex(max($oCol)).max($oRow);
						$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($cellIntersect));
						$stack->push('Value',$cellIntersect,$cellRef);
						break;
				}

			// if the token is a unary operator, pop one value off the stack, do the operation, and push it back on
			} elseif (($token === '~') || ($token === '%')) {
//				echo 'Token is a unary operator<br />';
				if (($arg = $stack->pop()) === NULL) return $this->_raiseFormulaError('Internal error - Operand value missing from stack');
				$arg = $arg['value'];
				if ($token === '~') {
//					echo 'Token is a negation operator<br />';
					$this->_debugLog->writeDebugLog('Evaluating Negation of ', $this->_showValue($arg));
					$multiplier = -1;
				} else {
//					echo 'Token is a percentile operator<br />';
					$this->_debugLog->writeDebugLog('Evaluating Percentile of ', $this->_showValue($arg));
					$multiplier = 0.01;
				}
				if (is_array($arg)) {
					self::_checkMatrixOperands($arg,$multiplier,2);
					try {
						$matrix1 = new PHPExcel_Shared_JAMA_Matrix($arg);
						$matrixResult = $matrix1->arrayTimesEquals($multiplier);
						$result = $matrixResult->getArray();
					} catch (PHPExcel_Exception $ex) {
						$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
						$result = '#VALUE!';
					}
					$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
					$stack->push('Value',$result);
				} else {
					$this->_executeNumericBinaryOperation($cellID,$multiplier,$arg,'*','arrayTimesEquals',$stack);
				}

			} elseif (preg_match('/^'.self::CALCULATION_REGEXP_CELLREF.'$/i', $token, $matches)) {
				$cellRef = NULL;
//				echo 'Element '.$token.' is a Cell reference<br />';
				if (isset($matches[8])) {
//					echo 'Reference is a Range of cells<br />';
					if ($pCell === NULL) {
//						We can't access the range, so return a REF error
						$cellValue = PHPExcel_Calculation_Functions::REF();
					} else {
						$cellRef = $matches[6].$matches[7].':'.$matches[9].$matches[10];
						if ($matches[2] > '') {
							$matches[2] = trim($matches[2],"\"'");
							if ((strpos($matches[2],'[') !== FALSE) || (strpos($matches[2],']') !== FALSE)) {
								//	It's a Reference to an external workbook (not currently supported)
								return $this->_raiseFormulaError('Unable to access External Workbook');
							}
							$matches[2] = trim($matches[2],"\"'");
//							echo '$cellRef='.$cellRef.' in worksheet '.$matches[2].'<br />';
							$this->_debugLog->writeDebugLog('Evaluating Cell Range ', $cellRef, ' in worksheet ', $matches[2]);
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($matches[2]), FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cells ', $cellRef, ' in worksheet ', $matches[2], ' is ', $this->_showTypeDetails($cellValue));
//							$cellRef = $matches[2].'!'.$cellRef;
						} else {
//							echo '$cellRef='.$cellRef.' in current worksheet<br />';
							$this->_debugLog->writeDebugLog('Evaluating Cell Range ', $cellRef, ' in current worksheet');
							if ($pCellParent !== NULL) {
								$cellValue = $this->extractCellRange($cellRef, $pCellWorksheet, FALSE);
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cells ', $cellRef, ' is ', $this->_showTypeDetails($cellValue));
						}
					}
				} else {
//					echo 'Reference is a single Cell<br />';
					if ($pCell === NULL) {
//						We can't access the cell, so return a REF error
						$cellValue = PHPExcel_Calculation_Functions::REF();
					} else {
						$cellRef = $matches[6].$matches[7];
						if ($matches[2] > '') {
							$matches[2] = trim($matches[2],"\"'");
							if ((strpos($matches[2],'[') !== FALSE) || (strpos($matches[2],']') !== FALSE)) {
								//	It's a Reference to an external workbook (not currently supported)
								return $this->_raiseFormulaError('Unable to access External Workbook');
							}
//							echo '$cellRef='.$cellRef.' in worksheet '.$matches[2].'<br />';
							$this->_debugLog->writeDebugLog('Evaluating Cell ', $cellRef, ' in worksheet ', $matches[2]);
							if ($pCellParent !== NULL) {
								$cellSheet = $this->_workbook->getSheetByName($matches[2]);
								if ($cellSheet && $cellSheet->cellExists($cellRef)) {
									$cellValue = $this->extractCellRange($cellRef, $this->_workbook->getSheetByName($matches[2]), FALSE);
									$pCell->attach($pCellParent);
								} else {
									$cellValue = NULL;
								}
							} else {
								return $this->_raiseFormulaError('Unable to access Cell Reference');
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cell ', $cellRef, ' in worksheet ', $matches[2], ' is ', $this->_showTypeDetails($cellValue));
//							$cellRef = $matches[2].'!'.$cellRef;
						} else {
//							echo '$cellRef='.$cellRef.' in current worksheet<br />';
							$this->_debugLog->writeDebugLog('Evaluating Cell ', $cellRef, ' in current worksheet');
							if ($pCellParent->isDataSet($cellRef)) {
								$cellValue = $this->extractCellRange($cellRef, $pCellWorksheet, FALSE);
								$pCell->attach($pCellParent);
							} else {
								$cellValue = NULL;
							}
							$this->_debugLog->writeDebugLog('Evaluation Result for cell ', $cellRef, ' is ', $this->_showTypeDetails($cellValue));
						}
					}
				}
				$stack->push('Value',$cellValue,$cellRef);

			// if the token is a function, pop arguments off the stack, hand them to the function, and push the result back on
			} elseif (preg_match('/^'.self::CALCULATION_REGEXP_FUNCTION.'$/i', $token, $matches)) {
//				echo 'Token is a function<br />';
				$functionName = $matches[1];
				$argCount = $stack->pop();
				$argCount = $argCount['value'];
				if ($functionName != 'MKMATRIX') {
					$this->_debugLog->writeDebugLog('Evaluating Function ', self::_localeFunc($functionName), '() with ', (($argCount == 0) ? 'no' : $argCount), ' argument', (($argCount == 1) ? '' : 's'));
				}
				if ((isset(self::$_PHPExcelFunctions[$functionName])) || (isset(self::$_controlFunctions[$functionName]))) {	// function
					if (isset(self::$_PHPExcelFunctions[$functionName])) {
						$functionCall = self::$_PHPExcelFunctions[$functionName]['functionCall'];
						$passByReference = isset(self::$_PHPExcelFunctions[$functionName]['passByReference']);
						$passCellReference = isset(self::$_PHPExcelFunctions[$functionName]['passCellReference']);
					} elseif (isset(self::$_controlFunctions[$functionName])) {
						$functionCall = self::$_controlFunctions[$functionName]['functionCall'];
						$passByReference = isset(self::$_controlFunctions[$functionName]['passByReference']);
						$passCellReference = isset(self::$_controlFunctions[$functionName]['passCellReference']);
					}
					// get the arguments for this function
//					echo 'Function '.$functionName.' expects '.$argCount.' arguments<br />';
					$args = $argArrayVals = array();
					for ($i = 0; $i < $argCount; ++$i) {
						$arg = $stack->pop();
						$a = $argCount - $i - 1;
						if (($passByReference) &&
							(isset(self::$_PHPExcelFunctions[$functionName]['passByReference'][$a])) &&
							(self::$_PHPExcelFunctions[$functionName]['passByReference'][$a])) {
							if ($arg['reference'] === NULL) {
								$args[] = $cellID;
								if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($cellID); }
							} else {
								$args[] = $arg['reference'];
								if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($arg['reference']); }
							}
						} else {
							$args[] = self::_unwrapResult($arg['value']);
							if ($functionName != 'MKMATRIX') { $argArrayVals[] = $this->_showValue($arg['value']); }
						}
					}
					//	Reverse the order of the arguments
					krsort($args);
					if (($passByReference) && ($argCount == 0)) {
						$args[] = $cellID;
						$argArrayVals[] = $this->_showValue($cellID);
					}
//					echo 'Arguments are: ';
//					print_r($args);
//					echo '<br />';
					if ($functionName != 'MKMATRIX') {
						if ($this->_debugLog->getWriteDebugLog()) {
							krsort($argArrayVals);
							$this->_debugLog->writeDebugLog('Evaluating ', self::_localeFunc($functionName), '( ', implode(self::$_localeArgumentSeparator.' ',PHPExcel_Calculation_Functions::flattenArray($argArrayVals)), ' )');
						}
					}
					//	Process each argument in turn, building the return value as an array
//					if (($argCount == 1) && (is_array($args[1])) && ($functionName != 'MKMATRIX')) {
//						$operand1 = $args[1];
//						$this->_debugLog->writeDebugLog('Argument is a matrix: ', $this->_showValue($operand1));
//						$result = array();
//						$row = 0;
//						foreach($operand1 as $args) {
//							if (is_array($args)) {
//								foreach($args as $arg) {
//									$this->_debugLog->writeDebugLog('Evaluating ', self::_localeFunc($functionName), '( ', $this->_showValue($arg), ' )');
//									$r = call_user_func_array($functionCall,$arg);
//									$this->_debugLog->writeDebugLog('Evaluation Result for ', self::_localeFunc($functionName), '() function call is ', $this->_showTypeDetails($r));
//									$result[$row][] = $r;
//								}
//								++$row;
//							} else {
//								$this->_debugLog->writeDebugLog('Evaluating ', self::_localeFunc($functionName), '( ', $this->_showValue($args), ' )');
//								$r = call_user_func_array($functionCall,$args);
//								$this->_debugLog->writeDebugLog('Evaluation Result for ', self::_localeFunc($functionName), '() function call is ', $this->_showTypeDetails($r));
//								$result[] = $r;
//							}
//						}
//					} else {
					//	Process the argument with the appropriate function call
						if ($passCellReference) {
							$args[] = $pCell;
						}
						if (strpos($functionCall,'::') !== FALSE) {
							$result = call_user_func_array(explode('::',$functionCall),$args);
						} else {
							foreach($args as &$arg) {
								$arg = PHPExcel_Calculation_Functions::flattenSingleValue($arg);
							}
							unset($arg);
							$result = call_user_func_array($functionCall,$args);
						}
//					}
					if ($functionName != 'MKMATRIX') {
						$this->_debugLog->writeDebugLog('Evaluation Result for ', self::_localeFunc($functionName), '() function call is ', $this->_showTypeDetails($result));
					}
					$stack->push('Value',self::_wrapResult($result));
				}

			} else {
				// if the token is a number, boolean, string or an Excel error, push it onto the stack
				if (isset(self::$_ExcelConstants[strtoupper($token)])) {
					$excelConstant = strtoupper($token);
//					echo 'Token is a PHPExcel constant: '.$excelConstant.'<br />';
					$stack->push('Constant Value',self::$_ExcelConstants[$excelConstant]);
					$this->_debugLog->writeDebugLog('Evaluating Constant ', $excelConstant, ' as ', $this->_showTypeDetails(self::$_ExcelConstants[$excelConstant]));
				} elseif ((is_numeric($token)) || ($token === NULL) || (is_bool($token)) || ($token == '') || ($token[0] == '"') || ($token[0] == '#')) {
//					echo 'Token is a number, boolean, string, null or an Excel error<br />';
					$stack->push('Value',$token);
				// if the token is a named range, push the named range name onto the stack
				} elseif (preg_match('/^'.self::CALCULATION_REGEXP_NAMEDRANGE.'$/i', $token, $matches)) {
//					echo 'Token is a named range<br />';
					$namedRange = $matches[6];
//					echo 'Named Range is '.$namedRange.'<br />';
					$this->_debugLog->writeDebugLog('Evaluating Named Range ', $namedRange);
					$cellValue = $this->extractNamedRange($namedRange, ((NULL !== $pCell) ? $pCellWorksheet : NULL), FALSE);
					$pCell->attach($pCellParent);
					$this->_debugLog->writeDebugLog('Evaluation Result for named range ', $namedRange, ' is ', $this->_showTypeDetails($cellValue));
					$stack->push('Named Range',$cellValue,$namedRange);
				} else {
					return $this->_raiseFormulaError("undefined variable '$token'");
				}
			}
		}
		// when we're out of tokens, the stack should have a single element, the final result
		if ($stack->count() != 1) return $this->_raiseFormulaError("internal error");
		$output = $stack->pop();
		$output = $output['value'];

//		if ((is_array($output)) && (self::$returnArrayAsType != self::RETURN_ARRAY_AS_ARRAY)) {
//			return array_shift(PHPExcel_Calculation_Functions::flattenArray($output));
//		}
		return $output;
	}	//	function _processTokenStack()


	private function _validateBinaryOperand($cellID, &$operand, &$stack) {
		if (is_array($operand)) {
			if ((count($operand, COUNT_RECURSIVE) - count($operand)) == 1) {
				do {
					$operand = array_pop($operand);
				} while (is_array($operand));
			}
		}
		//	Numbers, matrices and booleans can pass straight through, as they're already valid
		if (is_string($operand)) {
			//	We only need special validations for the operand if it is a string
			//	Start by stripping off the quotation marks we use to identify true excel string values internally
			if ($operand > '' && $operand[0] == '"') { $operand = self::_unwrapResult($operand); }
			//	If the string is a numeric value, we treat it as a numeric, so no further testing
			if (!is_numeric($operand)) {
				//	If not a numeric, test to see if the value is an Excel error, and so can't be used in normal binary operations
				if ($operand > '' && $operand[0] == '#') {
					$stack->push('Value', $operand);
					$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($operand));
					return FALSE;
				} elseif (!PHPExcel_Shared_String::convertToNumberIfFraction($operand)) {
					//	If not a numeric or a fraction, then it's a text string, and so can't be used in mathematical binary operations
					$stack->push('Value', '#VALUE!');
					$this->_debugLog->writeDebugLog('Evaluation Result is a ', $this->_showTypeDetails('#VALUE!'));
					return FALSE;
				}
			}
		}

		//	return a true if the value of the operand is one that we can use in normal binary operations
		return TRUE;
	}	//	function _validateBinaryOperand()


	private function _executeBinaryComparisonOperation($cellID, $operand1, $operand2, $operation, &$stack, $recursingArrays=FALSE) {
		//	If we're dealing with matrix operations, we want a matrix result
		if ((is_array($operand1)) || (is_array($operand2))) {
			$result = array();
			if ((is_array($operand1)) && (!is_array($operand2))) {
				foreach($operand1 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operandData), ' ', $operation, ' ', $this->_showValue($operand2));
					$this->_executeBinaryComparisonOperation($cellID,$operandData,$operand2,$operation,$stack);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			} elseif ((!is_array($operand1)) && (is_array($operand2))) {
				foreach($operand2 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operand1), ' ', $operation, ' ', $this->_showValue($operandData));
					$this->_executeBinaryComparisonOperation($cellID,$operand1,$operandData,$operation,$stack);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			} else {
				if (!$recursingArrays) { self::_checkMatrixOperands($operand1,$operand2,2); }
				foreach($operand1 as $x => $operandData) {
					$this->_debugLog->writeDebugLog('Evaluating Comparison ', $this->_showValue($operandData), ' ', $operation, ' ', $this->_showValue($operand2[$x]));
					$this->_executeBinaryComparisonOperation($cellID,$operandData,$operand2[$x],$operation,$stack,TRUE);
					$r = $stack->pop();
					$result[$x] = $r['value'];
				}
			}
			//	Log the result details
			$this->_debugLog->writeDebugLog('Comparison Evaluation Result is ', $this->_showTypeDetails($result));
			//	And push the result onto the stack
			$stack->push('Array',$result);
			return TRUE;
		}

		//	Simple validate the two operands if they are string values
		if (is_string($operand1) && $operand1 > '' && $operand1[0] == '"') { $operand1 = self::_unwrapResult($operand1); }
		if (is_string($operand2) && $operand2 > '' && $operand2[0] == '"') { $operand2 = self::_unwrapResult($operand2); }

		// Use case insensitive comparaison if not OpenOffice mode
		if (PHPExcel_Calculation_Functions::getCompatibilityMode() != PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE)
		{
			if (is_string($operand1)) {
				$operand1 = strtoupper($operand1);
			}

			if (is_string($operand2)) {
				$operand2 = strtoupper($operand2);
			}
		}

		$useLowercaseFirstComparison = is_string($operand1) && is_string($operand2) && PHPExcel_Calculation_Functions::getCompatibilityMode() == PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE;

		//	execute the necessary operation
		switch ($operation) {
			//	Greater than
			case '>':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) > 0;
				} else {
					$result = ($operand1 > $operand2);
				}
				break;
			//	Less than
			case '<':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) < 0;
				} else {
					$result = ($operand1 < $operand2);
				}
				break;
			//	Equality
			case '=':
				$result = ($operand1 == $operand2);
				break;
			//	Greater than or equal
			case '>=':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) >= 0;
				} else {
					$result = ($operand1 >= $operand2);
				}
				break;
			//	Less than or equal
			case '<=':
				if ($useLowercaseFirstComparison) {
					$result = $this->strcmpLowercaseFirst($operand1, $operand2) <= 0;
				} else {
					$result = ($operand1 <= $operand2);
				}
				break;
			//	Inequality
			case '<>':
				$result = ($operand1 != $operand2);
				break;
		}

		//	Log the result details
		$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
		//	And push the result onto the stack
		$stack->push('Value',$result);
		return TRUE;
	}	//	function _executeBinaryComparisonOperation()

	/**
	 * Compare two strings in the same way as strcmp() except that lowercase come before uppercase letters
	 * @param string $str1
	 * @param string $str2
	 * @return integer
	 */
	private function strcmpLowercaseFirst($str1, $str2)
	{
		$from = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$to = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$inversedStr1 = strtr($str1, $from, $to);
		$inversedStr2 = strtr($str2, $from, $to);

		return strcmp($inversedStr1, $inversedStr2);
	}

	private function _executeNumericBinaryOperation($cellID,$operand1,$operand2,$operation,$matrixFunction,&$stack) {
		//	Validate the two operands
		if (!$this->_validateBinaryOperand($cellID,$operand1,$stack)) return FALSE;
		if (!$this->_validateBinaryOperand($cellID,$operand2,$stack)) return FALSE;

		//	If either of the operands is a matrix, we need to treat them both as matrices
		//		(converting the other operand to a matrix if need be); then perform the required
		//		matrix operation
		if ((is_array($operand1)) || (is_array($operand2))) {
			//	Ensure that both operands are arrays/matrices of the same size
			self::_checkMatrixOperands($operand1, $operand2, 2);

			try {
				//	Convert operand 1 from a PHP array to a matrix
				$matrix = new PHPExcel_Shared_JAMA_Matrix($operand1);
				//	Perform the required operation against the operand 1 matrix, passing in operand 2
				$matrixResult = $matrix->$matrixFunction($operand2);
				$result = $matrixResult->getArray();
			} catch (PHPExcel_Exception $ex) {
				$this->_debugLog->writeDebugLog('JAMA Matrix Exception: ', $ex->getMessage());
				$result = '#VALUE!';
			}
		} else {
			if ((PHPExcel_Calculation_Functions::getCompatibilityMode() != PHPExcel_Calculation_Functions::COMPATIBILITY_OPENOFFICE) &&
				((is_string($operand1) && !is_numeric($operand1) && strlen($operand1)>0) || 
                 (is_string($operand2) && !is_numeric($operand2) && strlen($operand2)>0))) {
				$result = PHPExcel_Calculation_Functions::VALUE();
			} else {
				//	If we're dealing with non-matrix operations, execute the necessary operation
				switch ($operation) {
					//	Addition
					case '+':
						$result = $operand1 + $operand2;
						break;
					//	Subtraction
					case '-':
						$result = $operand1 - $operand2;
						break;
					//	Multiplication
					case '*':
						$result = $operand1 * $operand2;
						break;
					//	Division
					case '/':
						if ($operand2 == 0) {
							//	Trap for Divide by Zero error
							$stack->push('Value','#DIV/0!');
							$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails('#DIV/0!'));
							return FALSE;
						} else {
							$result = $operand1 / $operand2;
						}
						break;
					//	Power
					case '^':
						$result = pow($operand1, $operand2);
						break;
				}
			}
		}

		//	Log the result details
		$this->_debugLog->writeDebugLog('Evaluation Result is ', $this->_showTypeDetails($result));
		//	And push the result onto the stack
		$stack->push('Value',$result);
		return TRUE;
	}	//	function _executeNumericBinaryOperation()


	// trigger an error, but nicely, if need be
	protected function _raiseFormulaError($errorMessage) {
		$this->formulaError = $errorMessage;
		$this->_cyclicReferenceStack->clear();
		if (!$this->suppressFormulaErrors) throw new PHPExcel_Calculation_Exception($errorMessage);
		trigger_error($errorMessage, E_USER_ERROR);
	}	//	function _raiseFormulaError()


	/**
	 * Extract range values
	 *
	 * @param	string				&$pRange	String based range representation
	 * @param	PHPExcel_Worksheet	$pSheet		Worksheet
	 * @param	boolean				$resetLog	Flag indicating whether calculation log should be reset or not
	 * @return  mixed				Array of values in range if range contains more than one element. Otherwise, a single value is returned.
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function extractCellRange(&$pRange = 'A1', PHPExcel_Worksheet $pSheet = NULL, $resetLog = TRUE) {
		// Return value
		$returnValue = array ();

//		echo 'extractCellRange('.$pRange.')',PHP_EOL;
		if ($pSheet !== NULL) {
			$pSheetName = $pSheet->getTitle();
//			echo 'Passed sheet name is '.$pSheetName.PHP_EOL;
//			echo 'Range reference is '.$pRange.PHP_EOL;
			if (strpos ($pRange, '!') !== false) {
//				echo '$pRange reference includes sheet reference',PHP_EOL;
				list($pSheetName,$pRange) = PHPExcel_Worksheet::extractSheetTitle($pRange, true);
//				echo 'New sheet name is '.$pSheetName,PHP_EOL;
//				echo 'Adjusted Range reference is '.$pRange,PHP_EOL;
				$pSheet = $this->_workbook->getSheetByName($pSheetName);
			}

			// Extract range
			$aReferences = PHPExcel_Cell::extractAllCellReferencesInRange($pRange);
			$pRange = $pSheetName.'!'.$pRange;
			if (!isset($aReferences[1])) {
				//	Single cell in range
				sscanf($aReferences[0],'%[A-Z]%d', $currentCol, $currentRow);
				$cellValue = NULL;
				if ($pSheet->cellExists($aReferences[0])) {
					$returnValue[$currentRow][$currentCol] = $pSheet->getCell($aReferences[0])->getCalculatedValue($resetLog);
				} else {
					$returnValue[$currentRow][$currentCol] = NULL;
				}
			} else {
				// Extract cell data for all cells in the range
				foreach ($aReferences as $reference) {
					// Extract range
					sscanf($reference,'%[A-Z]%d', $currentCol, $currentRow);
					$cellValue = NULL;
					if ($pSheet->cellExists($reference)) {
						$returnValue[$currentRow][$currentCol] = $pSheet->getCell($reference)->getCalculatedValue($resetLog);
					} else {
						$returnValue[$currentRow][$currentCol] = NULL;
					}
				}
			}
		}

		// Return
		return $returnValue;
	}	//	function extractCellRange()


	/**
	 * Extract range values
	 *
	 * @param	string				&$pRange	String based range representation
	 * @param	PHPExcel_Worksheet	$pSheet		Worksheet
	 * @return  mixed				Array of values in range if range contains more than one element. Otherwise, a single value is returned.
	 * @param	boolean				$resetLog	Flag indicating whether calculation log should be reset or not
	 * @throws	PHPExcel_Calculation_Exception
	 */
	public function extractNamedRange(&$pRange = 'A1', PHPExcel_Worksheet $pSheet = NULL, $resetLog = TRUE) {
		// Return value
		$returnValue = array ();

//		echo 'extractNamedRange('.$pRange.')<br />';
		if ($pSheet !== NULL) {
			$pSheetName = $pSheet->getTitle();
//			echo 'Current sheet name is '.$pSheetName.'<br />';
//			echo 'Range reference is '.$pRange.'<br />';
			if (strpos ($pRange, '!') !== false) {
//				echo '$pRange reference includes sheet reference',PHP_EOL;
				list($pSheetName,$pRange) = PHPExcel_Worksheet::extractSheetTitle($pRange, true);
//				echo 'New sheet name is '.$pSheetName,PHP_EOL;
//				echo 'Adjusted Range reference is '.$pRange,PHP_EOL;
				$pSheet = $this->_workbook->getSheetByName($pSheetName);
			}

			// Named range?
			$namedRange = PHPExcel_NamedRange::resolveRange($pRange, $pSheet);
			if ($namedRange !== NULL) {
				$pSheet = $namedRange->getWorksheet();
//				echo 'Named Range '.$pRange.' (';
				$pRange = $namedRange->getRange();
				$splitRange = PHPExcel_Cell::splitRange($pRange);
				//	Convert row and column references
				if (ctype_alpha($splitRange[0][0])) {
					$pRange = $splitRange[0][0] . '1:' . $splitRange[0][1] . $namedRange->getWorksheet()->getHighestRow();
				} elseif(ctype_digit($splitRange[0][0])) {
					$pRange = 'A' . $splitRange[0][0] . ':' . $namedRange->getWorksheet()->getHighestColumn() . $splitRange[0][1];
				}
//				echo $pRange.') is in sheet '.$namedRange->getWorksheet()->getTitle().'<br />';

//				if ($pSheet->getTitle() != $namedRange->getWorksheet()->getTitle()) {
//					if (!$namedRange->getLocalOnly()) {
//						$pSheet = $namedRange->getWorksheet();
//					} else {
//						return $returnValue;
//					}
//				}
			} else {
				return PHPExcel_Calculation_Functions::REF();
			}

			// Extract range
			$aReferences = PHPExcel_Cell::extractAllCellReferencesInRange($pRange);
//			var_dump($aReferences);
			if (!isset($aReferences[1])) {
				//	Single cell (or single column or row) in range
				list($currentCol,$currentRow) = PHPExcel_Cell::coordinateFromString($aReferences[0]);
				$cellValue = NULL;
				if ($pSheet->cellExists($aReferences[0])) {
					$returnValue[$currentRow][$currentCol] = $pSheet->getCell($aReferences[0])->getCalculatedValue($resetLog);
				} else {
					$returnValue[$currentRow][$currentCol] = NULL;
				}
			} else {
				// Extract cell data for all cells in the range
				foreach ($aReferences as $reference) {
					// Extract range
					list($currentCol,$currentRow) = PHPExcel_Cell::coordinateFromString($reference);
//					echo 'NAMED RANGE: $currentCol='.$currentCol.' $currentRow='.$currentRow.'<br />';
					$cellValue = NULL;
					if ($pSheet->cellExists($reference)) {
						$returnValue[$currentRow][$currentCol] = $pSheet->getCell($reference)->getCalculatedValue($resetLog);
					} else {
						$returnValue[$currentRow][$currentCol] = NULL;
					}
				}
			}
//				print_r($returnValue);
//			echo '<br />';
		}

		// Return
		return $returnValue;
	}	//	function extractNamedRange()


	/**
	 * Is a specific function implemented?
	 *
	 * @param	string	$pFunction	Function Name
	 * @return	boolean
	 */
	public function isImplemented($pFunction = '') {
		$pFunction = strtoupper ($pFunction);
		if (isset(self::$_PHPExcelFunctions[$pFunction])) {
			return (self::$_PHPExcelFunctions[$pFunction]['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY');
		} else {
			return FALSE;
		}
	}	//	function isImplemented()


	/**
	 * Get a list of all implemented functions as an array of function objects
	 *
	 * @return	array of PHPExcel_Calculation_Function
	 */
	public function listFunctions() {
		// Return value
		$returnValue = array();
		// Loop functions
		foreach(self::$_PHPExcelFunctions as $functionName => $function) {
			if ($function['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY') {
				$returnValue[$functionName] = new PHPExcel_Calculation_Function($function['category'],
																				$functionName,
																				$function['functionCall']
																			   );
			}
		}

		// Return
		return $returnValue;
	}	//	function listFunctions()


	/**
	 * Get a list of all Excel function names
	 *
	 * @return	array
	 */
	public function listAllFunctionNames() {
		return array_keys(self::$_PHPExcelFunctions);
	}	//	function listAllFunctionNames()

	/**
	 * Get a list of implemented Excel function names
	 *
	 * @return	array
	 */
	public function listFunctionNames() {
		// Return value
		$returnValue = array();
		// Loop functions
		foreach(self::$_PHPExcelFunctions as $functionName => $function) {
			if ($function['functionCall'] != 'PHPExcel_Calculation_Functions::DUMMY') {
				$returnValue[] = $functionName;
			}
		}

		// Return
		return $returnValue;
	}	//	function listFunctionNames()

}	//	class PHPExcel_Calculation


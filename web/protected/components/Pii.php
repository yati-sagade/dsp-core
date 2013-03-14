<?php
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Pii.php
 *
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 * Copyright (c) 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright (c) 2012-2013 by DreamFactory Software, Inc. All rights reserved.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Pii extends \CHtml
{
	//********************************************************************************
	//* Members
	//********************************************************************************

	/**
	 * @var \CHttpRequest Cache the current request
	 */
	protected static $_thisRequest = null;
	/**
	 * @var \CClientScript Cache the client script object for speed
	 */
	protected static $_clientScript = null;
	/**
	 * @var \CWebUser Cache the user object for speed
	 */
	protected static $_thisUser = null;
	/**
	 * @var \CController Cache the current controller for speed
	 */
	protected static $_thisController = null;
	/**
	 * @var \CAttributeCollection Cache the application parameters for speed
	 */
	protected static $_appParameters = null;
	/**
	 * @var array An array of class names to search in for missing methods
	 */
	protected static $_classPath = array();

	//********************************************************************************
	//* Public Methods
	//********************************************************************************

	/**
	 * Bootstraps the Yii application, setting all the necessary junk
	 *
	 * @param string                         $docRoot The document root of the web site
	 * @param \Composer\Autoload\ClassLoader $autoloader
	 * @param bool                           $createApp
	 * @param bool                           $runApp
	 *
	 * @return void
	 */
	public static function run( $docRoot, $autoloader = null, $createApp = true, $runApp = true )
	{
		if ( null === $autoloader )
		{
			/** @noinspection PhpIncludeInspection */
			$autoloader = require_once( dirname( $docRoot ) . '/vendor/autoload.php' );
		}

		$_dspName = null;

		$_basePath = dirname( $docRoot );
		$_appMode = ( 'cli' == PHP_SAPI ? 'console' : 'web' );
		$_class = ( 'cli' == PHP_SAPI ? 'CConsoleApplication' : 'CWebApplication' );
		$_configPath = $_basePath . '/config';
		$_configFile = $_configPath . '/' . $_appMode . '.php';
		$_logPath = $_basePath . '/log';

		$_dspName = static::_determineHostName();

		$_logName = $_appMode . '.' . $_dspName . '.log';
		$_logFile = $_logPath . '/' . $_logName;

		//	Create an alias for our configuration directory
		static::alias( 'application.config', $_configPath );

		\Kisma::set( 'app.app_path', $_basePath . '/web' );
		\Kisma::set( 'app.config_path', $_configPath );
		\Kisma::set( 'app.log_path', $_logPath );
		\Kisma::set( 'app.log_file', $_logFile );
		\Kisma::set( 'app.template_path', $_configPath . '/templates' );
		\Kisma::set( 'app.vendor_path', $_basePath . '/vendor' );
		\Kisma::set( 'app.autoloader', $autoloader );
		\Kisma::set( 'app.dsp_name', $_dspName );
		\Kisma::set( 'app.fabric_hosted', $_isFabric = file_exists( '/var/www/.fabric_hosted' ) );

		//	And our log
		Log::setDefaultLog( $_logFile );

		//	Create the application and run!
		if ( $createApp )
		{
			$_app = \Yii::createApplication( $_class, $_configFile );

			if ( $runApp )
			{
				static::app( $_app )->run();
			}
		}
	}

	/**
	 * Checks to see if the passed in data is an Url
	 *
	 * @param string $data
	 *
	 * @return boolean
	 */
	public static function isUrl( $data )
	{
		return ( ( @parse_url( $data ) ) ? true : false );
	}

	/**
	 * Checks for an empty variable. Useful because the PHP empty() function cannot be reliably used with overridden __get methods.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isEmpty( $value )
	{
		return empty( $value );
	}

	/**
	 * Go out and pull the {@link http://gravatar.com/ gravatar} url for the specified email address.
	 *
	 * @param string  $email
	 * @param integer $size   The size of the image to return from 1px to 512px. Defaults to 80
	 * @param string  $rating The rating of the image to return: G, PG, R, or X. Defaults to G
	 *
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	public static function getGravatarUrl( $email, $size = 80, $rating = 'g' )
	{
		$rating = strtolower( $rating[0] );
		$size = intval( $size );

		if ( $size < 1 || $size > 512 )
		{
			throw new \InvalidArgumentException( 'The parameter "$size" must be between 1 and 512.' );
		}

		if ( !in_array( $rating, array( 'g', 'pg', 'r', 'x' ) ) )
		{
			throw new \InvalidArgumentException( 'The parameter "$rating" may only be "G", "PG", "R", or "X".' );
		}

		return 'http://www.gravatar.com/avatar/' . md5( strtolower( $email ) ) . '.jpg?s=' . $size . '&r=' . $rating;
	}

	//********************************************************************************
	//* Yii Convenience Mappings
	//********************************************************************************

	/**
	 * Shorthand version of Yii::app() with caching. Ya know, for speed!
	 *
	 * @param \CApplication|\CConsoleApplication|\CWebApplication|null $app
	 *
	 * @return \CConsoleApplication|\CWebApplication
	 */
	public static function app( $app = null )
	{
		/** @var $_thisApp \CApplication|\CWebApplication|\CConsoleApplication */
		static $_thisApp = null;

		if ( !empty( $_thisApp ) )
		{
			return $_thisApp;
		}

		//	Set other dependencies
		if ( null !== $app )
		{
			$_thisApp = $app;
		}
		else
		{
			$_thisApp = \Yii::app();
		}

		//	Non-CLI requests have clientScript and a user maybe
		if ( 'cli' != PHP_SAPI )
		{
			static::$_clientScript = $_thisApp->getClientScript();
			static::$_thisUser = $_thisApp->getUser();
		}

		static::$_thisRequest = $_thisApp->getRequest();
		static::$_appParameters = $_thisApp->getParams();

		return $_thisApp;
	}

	/**
	 * @param string $prefix      If specified, only parameters with this prefix will be returned
	 * @param bool   $regex       If true, $prefix will be treated as a regex pattern
	 *
	 * @return array
	 */
	public static function params( $prefix = null, $regex = false )
	{
		if ( null !== $prefix )
		{
			$_parameters = array();

			if ( false === $regex )
			{
				//	Make sure a trailing dot is added to prefix...
				$prefix = trim( strtolower( rtrim( $prefix, ' .' ) . '.' ) );
			}

			foreach ( static::$_appParameters as $_key => $_value )
			{
				if ( false !== $regex )
				{
					if ( 1 != preg_match( $prefix, $_key, $_matches ) )
					{
						continue;
					}

					$_parameters[str_ireplace( $_matches[0], null, $_key )] = $_value;
				}
				elseif ( false !== stripos( $_key, $prefix, 0 ) )
				{
					$_parameters[str_ireplace( $prefix, null, $_key )] = $_value;
				}
			}

			return $_parameters;
		}

		return static::$_appParameters;
	}

	/**
	 * @param string $db
	 *
	 * @return \PDO
	 */
	public static function pdo( $db = 'db' )
	{
		return static::db( $db )->getPdoInstance();
	}

	/**
	 * Shorthand version of Yii::app()->getController()
	 *
	 * @return \CController|\CBaseController|BaseDreamController
	 */
	public static function controller()
	{
		return static::$_thisController ? : ( static::$_thisController = static::app()->getController() );
	}

	/**
	 * Shorthand version of Yii::app()->getName()
	 *
	 * @param bool $notEncoded
	 *
	 * @return string
	 */
	public static function appName( $notEncoded = false )
	{
		return $notEncoded ? static::app()->name : static::encode( static::app()->name );
	}

	/**
	 * Convenience method returns the current page title
	 *
	 * @see CController::pageTitle
	 * @see CHtml::encode
	 *
	 * @param $notEncoded bool
	 *
	 * @return string
	 */
	public static function pageTitle( $notEncoded = false )
	{
		return $notEncoded ? static::controller()->getPageTitle() : static::encode( static::controller()->getPageTitle() );
	}

	/**
	 * Convenience method Returns the base url of the current app
	 *
	 * @param $absolute bool
	 *
	 * @return string
	 */
	public static function baseUrl( $absolute = false )
	{
		return static::app()->getBaseUrl( $absolute );
	}

	/**
	 * Convenience method Returns the base path of the current app
	 *
	 * @return string
	 */
	public static function basePath()
	{
		return static::app()->getBasePath();
	}

	/***
	 * Retrieves and caches the Yii ClientScript object
	 *
	 * @return \CClientScript
	 */
	public static function clientScript()
	{
		return static::$_clientScript;
	}

	/**
	 * Terminates the application.
	 * This method replaces PHP's exit() function by calling {@link onEndRequest} before exiting.
	 *
	 * @param integer $status exit status (value 0 means normal exit while other values mean abnormal exit).
	 * @param boolean $exit   whether to exit the current request. This parameter has been available since version 1.1.5. It defaults to true,
	 *                        meaning the PHP's exit() function will be called at the end of this method.
	 */
	public static function end( $status = 0, $exit = true )
	{
		static::app()->end( $status, $exit );
	}

	/**
	 * @param string $id
	 * @param bool   $createIfNull
	 *
	 * @return \CComponent The component, if found
	 */
	public static function component( $id, $createIfNull = true )
	{
		return static::app()->getComponent( $id, $createIfNull );
	}

	/**
	 * @param string $name
	 *
	 * @return \CDbConnection the database connection
	 */
	public static function db( $name = 'db' )
	{
		return static::component( $name );
	}

	/**
	 * Registers a javascript file.
	 *
	 * @internal param $string \URL of the javascript file
	 * @internal param $integer \the position of the JavaScript code. Valid values include the following:
	 * <ul>
	 * <li>CClientScript::POS_HEAD : the script is inserted in the head section right before the title element.</li>
	 * <li>CClientScript::POS_BEGIN : the script is inserted at the beginning of the body section.</li>
	 * <li>CClientScript::POS_END : the script is inserted at the end of the body section.</li>
	 * </ul>
	 *
	 * @param string|array $urlList
	 * @param int          $pagePosition
	 *
	 * @return \CClientScript
	 */
	public static function scriptFile( $urlList, $pagePosition = \CClientScript::POS_HEAD )
	{
		//	Need external library?
		foreach ( Option::clean( $urlList ) as $_url )
		{
			if ( !static::$_clientScript->isScriptFileRegistered( $_url ) )
			{
				static::$_clientScript->registerScriptFile( $_url, $pagePosition );
			}
		}

		return static::$_clientScript;
	}

	/**
	 * Registers a CSS file
	 *
	 * @param string $urlList
	 * @param string $media that the CSS file should be applied to. If empty, it means all media types.
	 *
	 * @return \CClientScript|null|string
	 */
	public static function cssFile( $urlList, $media = null )
	{
		foreach ( Option::clean( $urlList ) as $_url )
		{
			if ( !static::$_clientScript->isCssFileRegistered( $_url ) )
			{
				static::$_clientScript->registerCssFile( $_url, $media );
			}
		}

		return static::$_clientScript;
	}

	/**
	 * Registers a piece of CSS code.
	 *
	 * @param string ID that uniquely identifies this piece of CSS code
	 * @param string the CSS code
	 * @param string media that the CSS code should be applied to. If empty, it means all media types.
	 *
	 * @return \CClientScript|null
	 * @access public
	 * @static
	 */
	public static function css( $id, $css, $media = null )
	{
		if ( !static::$_clientScript->isCssRegistered( $id ) )
		{
			static::$_clientScript->registerCss( $id, $css, $media );
		}

		return static::$_clientScript;
	}

	/**
	 * Registers a piece of javascript code.
	 *
	 * @param string  ID that uniquely identifies this piece of JavaScript code
	 * @param string  the javascript code
	 * @param integer the position of the JavaScript code. Valid values include the following:
	 * <ul>
	 * <li>CClientScript::POS_HEAD : the script is inserted in the head section right before the title element.</li>
	 * <li>CClientScript::POS_BEGIN : the script is inserted at the beginning of the body section.</li>
	 * <li>CClientScript::POS_END : the script is inserted at the end of the body section.</li>
	 * <li>CClientScript::POS_LOAD : the script is inserted in the window.onload() function.</li>
	 * <li>CClientScript::POS_READY : the script is inserted in the jQuery's ready function.</li>
	 * </ul>
	 *
	 * @return \CClientScript|null|string
	 * @access public
	 * @static
	 */
	public static function script( $id, $script, $position = \CClientScript::POS_READY )
	{
		if ( !static::$_clientScript->isScriptRegistered( $id ) )
		{
			static::$_clientScript->registerScript(
				$id,
				$script,
				$position
			);
		}

		return static::$_clientScript;
	}

	/**
	 * Registers a meta tag that will be inserted in the head section (right before the title element) of the resulting page.
	 *
	 * @param string content attribute of the meta tag
	 * @param string name attribute of the meta tag. If null, the attribute will not be generated
	 * @param string http-equiv attribute of the meta tag. If null, the attribute will not be generated
	 * @param array  other options in name-value pairs (e.g. 'scheme', 'lang')
	 *
	 * @return \CClientScript|null
	 * @access public
	 * @static
	 */
	public static function metaTag( $content, $name = null, $httpEquivalent = null, $attributes = array() )
	{
		static::$_clientScript->registerMetaTag( $content, $name, $httpEquivalent, $attributes );

		return static::$_clientScript;
	}

	/**
	 * Creates a relative URL based on the given controller and action information.
	 *
	 * @param string the URL route. This should be in the format of 'ControllerID/ActionID'.
	 * @param array  additional GET parameters (name=>value). Both the name and value will be URL-encoded.
	 * @param string the token separating name-value pairs in the URL.
	 *
	 * @return string the constructed URL
	 */
	public static function url( $route, $options = array(), $ampersand = '&' )
	{
		return static::app()->createUrl( $route, $options, $ampersand );
	}

	/**
	 * Returns the current request. Equivalent of {@link CApplication::getRequest}
	 *
	 * @see CApplication::getRequest
	 * @return \CHttpRequest
	 */
	public static function request()
	{
		return static::$_thisRequest;
	}

	/**
	 * Returns the current user identity.
	 *
	 * @return \CUserIdentity
	 */
	public static function identity()
	{
		return self::component( 'user' );
	}

	/**
	 * Returns the current user. Equivalent of {@link CWebApplication::getUser}
	 *
	 * @return \CWebUser
	 */
	public static function user()
	{
		return static::$_thisUser;
	}

	/**
	 * Returns boolean indicating if user is logged in or not
	 *
	 * @return boolean
	 */
	public static function guest()
	{
		return static::user()->getIsGuest();
	}

	/**
	 * Returns application parameters or default value if not found
	 *
	 * @param string $paramName
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	public static function getParam( $paramName, $defaultValue = null )
	{
		if ( static::$_appParameters && static::$_appParameters->contains( $paramName ) )
		{
			return static::$_appParameters->itemAt( $paramName );
		}

		return $defaultValue;
	}

	/**
	 * Convenience access to CAssetManager::publish()
	 *
	 * Publishes a file or a directory.
	 * This method will copy the specified asset to a web accessible directory
	 * and return the URL for accessing the published asset.
	 * <ul>
	 * <li>If the asset is a file, its file modification time will be checked
	 * to avoid unnecessary file copying;</li>
	 * <li>If the asset is a directory, all files and subdirectories under it will
	 * be published recursively. Note, in this case the method only checks the
	 * existence of the target directory to avoid repetitive copying.</li>
	 * </ul>
	 *
	 * @param string  the asset (file or directory) to be published
	 * @param boolean whether the published directory should be named as the hashed basename.
	 *                If false, the name will be the hashed dirname of the path being published.
	 *                Defaults to false. Set true if the path being published is shared among
	 *                different extensions.
	 * @param integer level of recursive copying when the asset is a directory.
	 *                Level -1 means publishing all subdirectories and files;
	 *                Level 0 means publishing only the files DIRECTLY under the directory;
	 *                level N means copying those directories that are within N levels.
	 *
	 * @return string an absolute URL to the published asset
	 * @throws CException if the asset to be published does not exist.
	 * @see CAssetManager::publish
	 */
	public static function publishAsset( $path, $hashByName = false, $level = -1 )
	{
		/** @noinspection PhpUndefinedMethodInspection */
		return static::app()->getAssetManager()->publish( $path, $hashByName, $level );
	}

	/**
	 * Performs a redirect. See {@link CHttpRequest::redirect}
	 *
	 * @param string  $url
	 * @param boolean $terminate
	 * @param int     $statusCode
	 *
	 * @see CHttpRequest::redirect
	 */
	public static function redirect( $url, $terminate = true, $statusCode = 302 )
	{
		static::$_thisRequest->redirect( $url, $terminate, $statusCode );
	}

	/**
	 * Returns the details about the error that is currently being handled.
	 * The error is returned in terms of an array, with the following information:
	 * <ul>
	 * <li>code - the HTTP status code (e.g. 403, 500)</li>
	 * <li>type - the error type (e.g. 'CHttpException', 'PHP Error')</li>
	 * <li>message - the error message</li>
	 * <li>file - the name of the PHP script file where the error occurs</li>
	 * <li>line - the line number of the code where the error occurs</li>
	 * <li>trace - the call stack of the error</li>
	 * <li>source - the context source code where the error occurs</li>
	 * </ul>
	 *
	 * @return array the error details. Null if there is no error.
	 */
	public static function error()
	{
		return static::app()->getErrorHandler()->getError();
	}

	/**
	 * Determine if PHP is running CLI mode or not
	 *
	 * @return boolean True if currently running in CLI
	 */
	public static function cli()
	{
		return ( 'cli' == PHP_SAPI );
	}

	/**
	 * Get or set a path alias. If $path is provided, this acts like a "setter" otherwise a "getter"
	 * Note, this method neither checks the existence of the path nor normalizes the path.
	 *
	 * @param string $alias    alias to the path
	 * @param string $path     the path corresponding to the alias. If this is null, the corresponding
	 *                         path alias will be removed.
	 * @param string $morePath When retrieving an alias, $morePath will be appended to the end
	 *
	 * @return mixed|null|string
	 */
	public static function alias( $alias, $path = null, $morePath = null )
	{
		if ( null !== $path )
		{
			\Yii::setPathOfAlias( $alias, $path );

			return $path;
		}

		$_path = \Yii::getPathOfAlias( $alias );

		if ( null !== $morePath )
		{
			$_path = trim( rtrim( $_path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . ltrim( $morePath, DIRECTORY_SEPARATOR ) );
		}

		return $_path;
	}

	/**
	 * @return boolean whether this is POST request.
	 */
	public static function postRequest()
	{
		static $_is = null;

		if ( null === $_is )
		{
			$_is = static::request()->getIsPostRequest();
		}

		return $_is;
	}

	/**
	 * @return boolean whether this is PUT request.
	 */
	public static function putRequest()
	{
		static $_is = null;

		if ( null === $_is )
		{
			$_is = static::request()->getIsPutRequest();
		}

		return $_is;
	}

	/**
	 * @return boolean whether this is DELETE request.
	 */
	public static function deleteRequest()
	{
		static $_is = null;

		if ( null === $_is )
		{
			$_is = static::request()->getIsDeleteRequest();
		}

		return $_is;
	}

	/**
	 * @return boolean whether this is DELETE request.
	 */
	public static function ajaxRequest()
	{
		static $_is = null;

		if ( null === $_is )
		{
			$_is = static::request()->getIsAjaxRequest();
		}

		return $_is;
	}

	/**
	 * Generic array sorter
	 *
	 * To sort a column in descending order, assign 'desc' to the column's value in the defining array:
	 *
	 * $_columnsToSort = array(
	 *    'date' => 'desc',
	 *    'lastName' => 'asc',
	 *    'firstName' => 'asc',
	 * );
	 *
	 * @param array $arrayToSort
	 * @param array $columnsToSort Array of columns in $arrayToSort to sort.
	 *
	 * @return boolean
	 */
	public static function arraySort( &$arrayToSort, $columnsToSort = array() )
	{
		//	Convert to an array
		if ( !empty( $columnsToSort ) && !is_array( $columnsToSort ) )
		{
			$columnsToSort = array( $columnsToSort );
		}

		//	Any fields?
		if ( !empty( $columnsToSort ) )
		{
			return usort(
				$arrayToSort,
				function ( $a, $b ) use ( $columnsToSort )
				{
					$_result = null;

					foreach ( $columnsToSort as $_column => $_order )
					{
						$_order = trim( strtolower( $_order ) );

						if ( is_numeric( $_column ) && !\Kisma\Core\Utility\Scalar::in( $_order, 'asc', 'desc' ) )
						{
							$_column = $_order;
							$_order = null;
						}

						if ( 'desc' == strtolower( $_order ) )
						{
							return strnatcmp( $b[$_column], $a[$_column] );
						}

						return strnatcmp( $a[$_column], $b[$_column] );
					}
				}
			);
		}

		return false;
	}

	/**
	 * Sorts an array by a single column
	 *
	 * @param array  $sourceArray
	 * @param string $column
	 * @param int    $sortDirection
	 *
	 * @return bool
	 */
	public static function array_multisort_column( &$sourceArray, $column, $sortDirection = SORT_ASC )
	{
		$_sortColumn = array();

		foreach ( $sourceArray as $_key => $_row )
		{
			$_sortColumn[$_key] = ( isset( $_row[$column] ) ? $_row[$column] : null );
		}

		return \array_multisort( $_sortColumn, $sortDirection, $sourceArray );
	}

	/**
	 * Serializer that can handle SimpleXmlElement objects
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function serialize( $value )
	{
		try
		{
			if ( $value instanceof \SimpleXMLElement )
			{
				return $value->asXML();
			}

			if ( is_object( $value ) )
			{
				return \serialize( $value );
			}
		}
		catch ( \Exception $_ex )
		{
		}

		return $value;
	}

	/**
	 * Unserializer that can handle SimpleXmlElement objects
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function unserialize( $value )
	{
		try
		{
			if ( static::serialized( $value ) )
			{
				if ( $value instanceof \SimpleXMLElement )
				{
					return \simplexml_load_string( $value );
				}

				return \unserialize( $value );
			}
		}
		catch ( \Exception $_ex )
		{
		}

		return $value;
	}

	/**
	 * Tests if a value needs unserialization
	 *
	 * @param mixed $value
	 *
	 * @return boolean
	 */
	public static function serialized( $value )
	{
		$_result = @\unserialize( $value );

		return !( false === $_result && $value != \serialize( false ) );
	}

	/**
	 * @param string $name
	 * @param mixed  $defaultValue
	 *
	 * @return mixed
	 */
	public static function getState( $name, $defaultValue = null )
	{
		return static::$_thisUser->getState( $name, $defaultValue );
	}

	/**
	 * @param string $name
	 * @param mixed  $value The value to store
	 * @param mixed  $defaultValue
	 */
	public static function setState( $name, $value, $defaultValue = null )
	{
		static::$_thisUser->setState( $name, $value, $defaultValue );
	}

	/**
	 * @param string $name
	 */
	public static function clearState( $name )
	{
		static::$_thisUser->setState( $name, null, null );
	}

	/**
	 * Stores a flash message.
	 * A flash message is available only in the current and the next requests.
	 *
	 * @param string $key
	 * @param string $message
	 * @param string $defaultValue
	 */
	public static function setFlash( $key, $message = null, $defaultValue = null )
	{
		static::$_thisUser->setFlash( $key, $message, $defaultValue );
	}

	/**
	 * Gets a stored flash message
	 * A flash message is available only in the current and the next requests.
	 *
	 * @param string  $key
	 * @param mixed   $defaultValue
	 * @param boolean $delete       If true, delete this flash message after accessing it.
	 *
	 * @return string
	 */
	public static function getFlash( $key, $defaultValue = null, $delete = true )
	{
		return static::$_thisUser->getFlash( $key, $defaultValue, $delete );
	}

	/**
	 * @return string
	 */
	protected static function _determineHostName()
	{
		if ( null === ( $_dspName = \Kisma::get( 'app.dsp_name' ) ) )
		{
			//	Figure out my name
			if ( isset( $_SERVER, $_SERVER['HTTP_HOST'] ) )
			{
				$_parts = explode( '.', $_SERVER['HTTP_HOST'] );

				if ( 4 == count( $_parts ) )
				{
					if ( 'cumulus' == ( $_dspName = $_parts[0] ) )
					{
						$_dspName = null;
					}
				}
			}
		}

		if ( empty( $_dspName ) )
		{
			$_dspName = str_replace( '.dreamfactory.com', null, gethostname() );
		}

		return $_dspName;
	}
}
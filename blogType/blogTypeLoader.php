<?php
/**
 * @name Load Blog Type
 * @abstract Processes incoming requests for IMS LTI and apply wordpress with blogType parametrer
 * @author Antoni Bertran (abertranb@uoc.edu)
 * @copyright 2010 Universitat Oberta de Catalunya
 * @license GPL
 * Date December 2010
*/
    
require_once (dirname(__FILE__).'/MessagesBlogType.php');
require_once (dirname(__FILE__).'/Constants.php');

class blogTypeLoader {
	
  var $blogType       = null;
  var $blogTypeClass  = null;
  var $error          = 0;
  var $error_miss     = null;
	
	public function __construct($context) {
		
		$this->blogType=trim($context->info['custom_blogtype']);
		//Si es buit posem el per defect
		
		if ($this->blogType=='') {
			$this->blogType = 'defaultType';
		}
		
	  if ( (isset($this->blogType)) && ($this->blogType != "") ) {
        $class_file = FOLDER_BASE_TYPES_BLTI.$this->blogType.'.php';
        if (file_exists($class_file)) {
        	
        	require_once ($class_file);
        	
        	if (class_exists($this->blogType)) {
        	
        		//fem el new
          		$this->blogTypeClass  = new $this->blogType();

        	} else {
        		$this->error = -1;
        		$this->error_miss = sprintf(ERROR_BLOG_TYPE_1,$this->blogType, $class_file);
        	}
        } else {
          $this->error = -2;
          $this->error_miss = sprintf(ERROR_BLOG_TYPE_2, $class_file);//'File '.$class_file.' not exists';
        }
    } else {
          $this->error = -3;
          $this->error_miss = ERROR_BLOG_TYPE_GENERAL;
    }
    
    return $this->blogTypeClass;
	}
	
	public function showErrorMessage() {
		if ($this->error<0)
		  echo '<font color="red">'.$this->error_mis.'</font>';
	}

	public function getCourseName($blti) {
		return $this->blogTypeClass->getCourseName($blti);
	}
	
	public function getCoursePath($blti, $siteUrlArray, $domain) {
		return $this->blogTypeClass->getCoursePath($blti, $siteUrlArray, $domain);
	}
	
	public function getMetaBlog($blti) {
		return $this->blogTypeClass->getMetaBlog($blti);
	}
	
	public function setLanguage($blti) {
		return $this->blogTypeClass->setLanguage($blti);
	}
	
	public function roleMapping($role, $blti) {
	     return $this->blogTypeClass->roleMapping($role, $blti); 
	}
	  
	public function loadPlugins() {
		return $this->blogTypeClass->loadPlugins(); 
	}
	  
	public function changeTheme() {
		return $this->blogTypeClass->changeTheme(); 
	}

    /**
     * This function contains the last actions before show blog
     */
    public function postActions($obj) {
    	return $this->blogTypeClass->postActions($obj); 
    }
    
    public function checkErrorCreatingBlog($blog_id, $path) {
    	return $this->blogTypeClass->checkErrorCreatingBlog($blog_id, $path); 
    }
	
}    
    ?>
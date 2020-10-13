<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	// LOW LEVEL methods - not unit testable -- directly interface with database -- no business logic allowed
	
	public function getProjectIDs() {
		
	}
	public function getDAGs() {
		
	}
	public function getEventIDs() {
		
	}
	public function getUADData() {
		
	}
	public function getEDCData() {
		
	}
	public function getUser() {
		
	}
	
	// HIGHER LEVEL methods -- unit testable -- do NOT interface with external data sources (db)
	public function authorizeUser() {
		
	}
	public function getRecords() {
		
	}
	public function getMySiteData() {
		
	}
	public function getAllSitesData() {
		
	}
	
	// hooks
	public function redcap_module_link_check_display($pid, $link) {
		$this->getUser();
		$this->authorizeUser();
		if ($this->user->authorized !== true)
			return false;
		return $link;
	}
}

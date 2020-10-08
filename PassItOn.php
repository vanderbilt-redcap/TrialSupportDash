<?php
namespace Vanderbilt\PassItOn;

class PassItOn extends \ExternalModules\AbstractExternalModule {
	// LOW LEVEL methods - not unit testable -- directly interface with database -- no business logic allowed
	public function getUser() {
		
	}
	public function getDAGs() {
		
	}
	public function getProjectIDs() {
		
	}
	public function getEventIDs() {
		
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
	public function showMySite() {
		
	}
	public function showAllSites() {
		
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

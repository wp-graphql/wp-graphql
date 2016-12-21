<?php
namespace DFM\WPGraphQL\Queries\Attachments;

use DFM\WPGraphQL\Queries\PostObject\PostObjectQuery;
use DFM\WPGraphQL\Types\AttachmentType;

class Query extends PostObjectQuery {

	public function getType() {
		return new AttachmentType();
	}

}
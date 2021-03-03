<?php

namespace MediaWiki\Extension\ScratchOAuth2\Common;

define('SOA2_COMMENTS_API', 'https://scratch.mit.edu/site-api/comments/user/%s?page=1&salt=%s');
define('SOA2_USERS_API', 'https://api.scratch.mit.edu/users/%s');
define('SOA2_PROFILE_URL', 'https://scratch.mit.edu/users/%s');
define('SOA2_USERNAME_REGEX', '%^[A-Za-z0-9_-]{3,20}$%');
define('SOA2_COMMENTS_REGEX', '%<div id="comments-\d+" class="comment +" data-comment-id="\d+">.*?<div class="actions-wrap">.*?<div class="name">\s+<a href="/users/([_a-zA-Z0-9-]+)">\1</a>\s+</div>\s+<div class="content">\s*(.*?)\s*</div>%s');
define('SOA2_SCOPES_SPLIT_REGEX', '%(?<=[a-z])(?=[, +])(?:\+|,? ?)(?=[a-z])%');
define('SOA2_SCOPES', [
	'identify',
]);
define('SOA2_AUTH_EXPIRY', 60*60);
define('SOA2_CODE_EXPIRY', 30*60);

class AppFlags {
	public const NAME_APPROVED = 1;
}
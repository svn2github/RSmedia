<?php

/*

	********** THIS IS WORK IN PROGRESS - MEH, Matthew Hinton, Montala **********

*/

include "../../include/db.php";
include "../../include/general.php";
include "../../include/authenticate.php";

$regex_email = "[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}";	// MEH: rudimentary regex to validate an email address - this is NOT a complete check

function comments_submit() 
	{		
		global $username;
		global $anonymous_login;
		global $userref;
		global $regex_email;

		$comment_flag_ref = getvalescaped("comment_flag_ref","");
		$comment_flag_reason = getvalescaped("comment_flag_reason","");
		
		if ($comment_flag_ref != "" && $comment_flag_reason != "") {
			
			setcookie("comment${comment_flag_ref}flagged", "true");			
			// TODO: send email here to - as specified in the globals
			exit;
		}
		
		if (											// we don't want to insert an empty comment or an orphan
			(getvalescaped("body","") == "") ||
			((getvalescaped("collection_ref","") == "") && (getvalescaped("resource_ref","") == "") && (getvalescaped("ref_parent","") == ""))
			)
			exit;
			
		if (!isset ($username) || (isset ($username) && $username == $anonymous_login))	// anonymous user		
			{
				if (getvalescaped("fullname","") == "" || getvalescaped("email","") == "") exit;			// must give fullname and email.  TODO: do a rudimentary email format check + chars > 200						
				$sql_fields = "fullname, email, website_url";				
				$sql_values = "'" . getvalescaped("fullname", "") . "','" . getvalescaped("email", "") . "','" . getvalescaped("website_url", "") . "'";													
			}
		else
			{
				$sql_fields = "user_ref";
				$sql_values = $userref;
			}		
		$sql = "insert into comment (ref_parent, collection_ref, resource_ref, {$sql_fields}, body) values ("	.
					getvalescaped("ref_parent", "NULL", true) . "," .
					getvalescaped("collection_ref", "NULL", true) . "," .
					getvalescaped("resource_ref", "NULL", true) . "," .					
					$sql_values . "," .					
					"'" . getvalescaped("body", "") ."'" .							
				")";		
		//file_put_contents("debug.txt", $sql);		// TODO: remove this debug line		
		sql_query($sql);		
	}

function comments_show($ref, $bcollection_mode = false, $bRecursive = true, $level = 1) 
	{					

	global $regex_email;
	
	# MEH, on behalf of Montala, 23-Jul-2013
	
	# ref 				= the reference of the resource, collection or the comment (if called from itself recursively) 
	# bcollection_mode	= boolean flag, false(default) == show comments for resources, true == show comments for collection
	# bRecursive		= flag to indicate whether to recursively show comments, defaults to true, will be set to false if depth limit reached
	# level				= used for recursion for display indentation etc.	
	
	global $username, $anonymous_login, $lang, $comments_max_characters, $comments_flat_view;
	
	$anonymous_mode = ((!isset ($username)) || (isset ($username) && $username == $anonymous_login));		// show extra fields if commenting anonymously
	
	if ($comments_flat_view) $bRecursive = false;	
			
	$bRecursive = $bRecursive && ($level < $GLOBALS['comments_responses_max_level']);				
	
	// set 'name' to either user.fullname, comment.fullname or default 'Anonymous'
	
	$sql = 	"select c.ref, c.ref_parent, c.created, c.body, c.website_url, c.email, parent.created 'responseToDateTime', " .			
			"IFNULL (IFNULL (u.fullname, c.fullname), '" . $lang['comments_anonymous-user'] . "') 'name' ," .  			
			"IFNULL (IFNULL (parent.fullname, parent.fullname), '" . $lang['comments_anonymous-user'] . "') 'responseToName' " .  			
			"from comment c left join (user u) on (c.user_ref = u.ref) left join (comment parent) on (c.ref_parent = parent.ref) ";
			
	$collection_ref = ($bcollection_mode) ? $ref : "";
	$resource_ref = ($bcollection_mode) ? "" : $ref;
	
	$collection_mode = $bcollection_mode ? "collection_mode=true" : "";		
			
	if ($level == 1) 
		{		
		
		// pass this JS function the "this" from the submit button in a form to post it via AJAX call, then refresh the "comments_container"
		
		echo<<<EOT

		<script type="text/javascript">
		
			var regexEmail = new RegExp ("${regex_email}");
		
			function validateAnonymousComment(obj) {							
				return (
					regexEmail.test (String(obj.email.value).trim()) &&
					String(obj.fullname.value).trim() != "" &&
					validateComment(obj)
				)				
			}
			
			function validateComment(obj) {
				return (String(obj.body.value).trim() != "");
			}
			
			function validateAnonymousFlag(obj) {
				return (
					regexEmail.test (String(obj.email.value).trim()) &&
					String(obj.fullname.value).trim() != "" &&
					validateFlag(obj)
				)
			}
			
			function validateFlag(obj) {
				return (String(obj.comment_flag_reason.value).trim() != "");				
			}
		
			function submitForm(obj) {			
				jQuery.post(
					'ajax/comments_handler.php',
					jQuery(obj).parent().serialize(),
					function()
					{
						jQuery.get(
							'ajax/comments_handler.php?ref={$ref}{$collection_mode}',
							function(data) 
							{
								jQuery('#comments_container').replaceWith(data);
							}
						)
					}
				);
			}
		</script>		

		<div id="comments_container">		
		<div id="comment_form">
			<form class="comment_form" action="javascript:void();" method="">
				<input id="comment_form_collection_ref" type="hidden" name="collection_ref" value="${collection_ref}"></input>
				<input id="comment_form_resource_ref" type="hidden" name="resource_ref" value="${resource_ref}"></input>				
				<textarea class="CommentFormBody" id="comment_form_body" name="body" maxlength="${comments_max_characters}" placeholder="${lang['comments_body-placeholder']}"></textarea>
EOT;
		
		if ($anonymous_mode)			
			{
			echo <<<EOT
				<br />
				<input class="CommentFormFullname" id="comment_form_fullname" type="text" name="fullname" placeholder="${lang['comments_fullname-placeholder']}"></input>
				<input class="CommentFormEmail" id="comment_form_email" type="text" name="email" placeholder="${lang['comments_email-placeholder']}"></input>
				<input class="CommentFormWebsiteURL" id="comment_form_website_url" type="text" name="website_url" placeholder="${lang['comments_website-url-placeholder']}"></input>
				
EOT;
			}		
			
		$validateFunction = $anonymous_mode ? "if (validateAnonymousComment(this.parentNode))" : "if (validateComment(this.parentNode))";
			
		echo<<<EOT
				<br />				
				<input class="CommentFormSubmit" type="submit" value="${lang['comments_submit-button-label']}" onClick="${validateFunction} { submitForm(this) } else { alert ('${lang['comments_validation-fields-failed']}'); } ;"></input>
			</form>			
		</div>	<!-- end of comments_container -->
EOT;
	
		$sql .= $bcollection_mode ? "where c.collection_ref=${ref}" : "where c.resource_ref=${ref}";  // first level will look for either collection or resource comments		
		if (!$comments_flat_view) $sql .= " and c.ref_parent is null";				
		}			
	else 
		{
		$sql .= "where c.ref_parent=${ref}";		// look for child comments, regardless of what type of comment
		}
	
	$sql .= " order by c.created desc";	
	$found_comments = sql_query($sql);
	
	foreach ($found_comments as $comment) 			
		{						
			
			$thisRef = $comment['ref'];
			
			echo "<div class='CommentEntry' id='comment${thisRef}' style='margin-left: " . ($level-1)*50 . "px;'>";	// indent for levels - this will always be zero if config $comments_flat_view=true						
			
			# ----- Information line
			
			echo "<div class='CommentEntryInfoContainer'>";			
			echo "<div class='CommentEntryInfo'>";						
			echo "<div class='CommentEntryInfoCommenter'>";			
			echo "<div class='CommentEntryInfoCommenterName'>" . htmlspecialchars($comment['name']) . "</div>";		
			if ($lang['comments_show-anonymous-email_address'] && $comment['email'] != "")
				{
				echo "<div class='CommentEntryInfoCommenterEmail'>" . htmlspecialchars ($comment['email']) . "</div>";
				}
			if  ($comment['website_url']!="")
				{
				echo "<div class='CommentEntryInfoCommenterWebsite'>" . htmlspecialchars ($comment['website_url']) . "</div>";
				}								
			echo "</div>";			
			echo "<div class='CommentEntryInfoDetails'>" . nicedate($comment["created"],true). " ";			
			if ($comment['responseToDateTime']!="")
				{
				$responseToName = htmlspecialchars ($comment['responseToName']);
				$responseToDateTime = nicedate($comment['responseToDateTime'], true);
				$jumpAnchorID = "comment" . $comment['ref_parent'];								
				echo $lang['comments_in-response-to'] . "<br /><a class='.smoothscroll' rel='' href='#${jumpAnchorID}'>${responseToName} " . $lang['comments_in-response-to-on'] . " ${responseToDateTime}</a>";				
				}						
			echo "</div>";	// end of CommentEntryInfoDetails		
			echo "<div class='CommentEntryInfoFlag'>";		
			if (getval("comment${thisRef}flagged",""))
				{
					echo "<div class='CommentFlagged'>${lang['comments_flag-has-been-flagged']}</div>";			
				} else {				
					echo<<<EOT
					<div class="CommentFlag">
						<a href="javascript:void(0)" onclick="jQuery('#CommentFlagContainer${thisRef}').toggle('fast');">${lang['comments_flag-this-comment']}</a>
					</div>
EOT;

				}				
			echo "</div>";		// end of CommentEntryInfoFlag
			echo "</div>";	// end of CommentEntryInfoLine			
			echo "</div>";	// end CommentEntryInfoContainer
			
			echo "<div class='CommentBody'>";			
			echo htmlspecialchars ($comment['body']);
			echo "</div>";			
			
			# ----- Form area
			
			$validateFunction = $anonymous_mode ? "if (validateAnonymousFlag(this.parentNode))" : "if (validateFlag(this.parentNode))";
			
			if (!getval("comment${thisRef}flagged",""))
				{
				echo<<<EOT
					
					<div id="CommentFlagContainer${thisRef}" style="display: none;">
						<form class="comment_form" action="javascript:void();" method="">
							<input type="hidden" name="comment_flag_ref" value="${thisRef}"></input>
							<textarea class="CommentFlagReason" name="comment_flag_reason" placeholder="${lang['comments_flag-reason-placeholder']}"></textarea><br />							
							<input class="CommentFlagFullname" id="comment_flag_fullname" type="text" name="fullname" placeholder="${lang['comments_fullname-placeholder']}"></input>
							<input class="CommentFlagEmail" id="comment_flag_email" type="text" name="email" placeholder="${lang['comments_email-placeholder']}"></input><br />							
							<input class="CommentFlagSubmit" type="submit" value="${lang['comments_submit-button-label']}" onClick="${validateFunction} { submitForm(this); } else { alert ('${lang['comments_validation-fields-failed']}') }"></input>
						</form>
					</div>				
EOT;

				}			
			
			$respond_div_id = "comment_respond_" . $comment['ref'];
			$ref_parent = $comment['ref'];
			
			echo "<div id='${respond_div_id}'>";		// start respond div
			echo "<a href='javascript:void(0)' onClick='
				jQuery(\"#{$respond_div_id}\").replaceWith(jQuery(\"#comment_form\").clone().attr(\"id\",\"${respond_div_id}\")); 
				jQuery(\"<input>\").attr({type: \"hidden\", name: \"ref_parent\", value: \"${ref_parent}\"}).appendTo(\"#${respond_div_id} .comment_form\");				
			'>&gt; " . $lang['comments_respond-to-this-comment'] . "</a>";			
			echo "</div>";		// end respond
							
			echo "</div>";		// end of CommentEntry
			
			if ($bRecursive) comments_show($comment['ref'], $bcollection_mode, true, $level+1, $comment['name'], $comment['created']);				

			
		}			
	}
	echo "</div>";  // end of comments_container
	
if ($_SERVER['REQUEST_METHOD'] == "POST") 
	{
	if (isset ($username)) comments_submit();
	} 
else 
	{
	$ref = (isset ($_GET['ref'])) ? $_GET['ref'] : "";
	$collection_mode = (isset ($_GET['collection_mode']) && $_GET['collection_mode']);				
	comments_show($ref, $collection_mode);				
	}
	
		
?>

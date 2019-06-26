# FormFuse Self Hosted Prefill Script
This script allows you to store the prefill information on your own server. When FormFuse forms are displayed a call is made to fetch any prefill information that is stored for the current user.  This can be done automatically by FormFuse or done outside of FormFuse using this script. Advantages to this approach include storing personal information on your servers and having full control over the security.


## Setup
Download the repository and upload to your server.  Open the prefill.php file and edit the variables outlined at the top. You will need to add your MySQL database login information as well as your Marketo API settings. These are the same as what you put into the 'Settings' area of FormFuse.com. (You may follow this guide to help get this information in your Marketo instance: http://hyperxmedia.com/umf-registration/umf_api_settings.pdf)

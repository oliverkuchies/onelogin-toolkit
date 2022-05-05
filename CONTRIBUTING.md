#Contributing Guidelines

##Adding new issues

###Bugs
When suggesting a bug, please use the following title structure:
``[BUG] OneLogin not authenticating when its Friday``

Please add the 'bug' label.

###Features
When suggesting a feature, please use the following title structure:
``[FEAT] Allow OneLogin to authenticate during Winter``

Please add the 'enhancement' label.

###Improvements
When suggesting an improvement, please use the following title structure:
``[IMPR] Clean up Oliver's messy code.``

Please add the 'enhancement' label.

##Modifying existing code

When modifying the existing code, please commit your changes to a new branch structured like the following:
`BUG/TICKET-ID/THIS-IS-MY-BUG-TITLE`

And be sure to add a commit title with the ticket name, and a brief description about the changes made to the code.

Once this is complete, please create a pull request and point it to the ``dev`` branch.

When reviewed by peers, the PR will be merged into dev branch. 

Dev branch will be merged into a new release every month (or less, depending on urgency).
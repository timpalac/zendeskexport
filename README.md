# Zen Desk Export
A PHP application that allows a user to plug and play simple API keys to export their ZenDesk tickets to a CSV

Head to https://developer.zendesk.com/rest_api/docs/core/introduction and read through the docs.  You'll need three pieces of data to get this up and running:
- your ZenDesk URL
- a login to that URL
- an API Key

Once you have those values entered on lines 2, 3, and 4 in tickets.php, you can export all your tickets via the simple interface, which allows for a start date, end date, and for tickets that were created or updated.

Just set up this file on any PHP server and plug in those values on lines 2, 3, and 4.  It uses cURL http://php.net/manual/en/book.curl.php to securely access the ZenDesk API and retrieve data.  

The application makes calls to the following APIs:
Tickets - https://developer.zendesk.com/rest_api/docs/core/tickets
Organizations - https://developer.zendesk.com/rest_api/docs/core/organizations
Users - https://developer.zendesk.com/rest_api/docs/core/users
Groups - https://developer.zendesk.com/rest_api/docs/core/groups
Ticket Comments - https://developer.zendesk.com/rest_api/docs/core/ticket_comments

From there, it exports everything into a CSV file, which can be used as you desire.  Software such as invoicing systems will accept a CSV file import so the fields can also be edited to match the API you're trying to import into.

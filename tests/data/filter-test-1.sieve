### Nextcloud Mail Filter ###

require ["imap4flags"]

# Filter: Filter 1
if allof (header :contains "subject" ["Hello Hello"], address :is :all "to" ["bob@acme.org"]) {
addflag "flagvar" ["Important"];
}

# Filter: Filter 2
if allof (header :contains "subject" ["Hello Hello"], address :is :all "to" ["bob@acme.org"]) {
addflag "flagvar" ["Important"];
}

# DATA: [{"id":"filter1000","name":"Filter 1","enable":true,"operator":"allof","tests":[{"id":"filter1000-test1","field":"subject","operator":"contains","value":"Hello Hello"},{"id":"filter1000-test2","field":"to","operator":"is","value":"bob@acme.org"}],"actions":[{"id":"filter1000-action1","type":"addflag","flag":"Important"},{"id":"filter1000-action2","type":"keep"}]},{"id":"filter2000","name":"Filter 2","enable":true,"operator":"allof","tests":[{"id":"filter2000-test3","field":"subject","operator":"contains","value":"Hello Hello"},{"id":"filter2000-test2","field":"to","operator":"is","value":"bob@acme.org"}],"actions":[{"id":"filter2000-action1","type":"addflag","flag":"Important"},{"id":"filter2000-action2","type":"keep"}]}]

### /Nextcloud Mail Filter ###

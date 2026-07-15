<?xml version="1.0" encoding="UTF-8"?>
{{-- NOTHING may precede the XML declaration — not even a Blade comment, whose trailing
     newline is enough to make the whole document malformed. Keep this comment below it. --}}
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
    <responseDate>{{ now()->toIso8601ZuluString() }}</responseDate>

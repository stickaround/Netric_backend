<row>
    <column>
        <tabs>
            <tab name='General'>
                <row>
                    <column>
                        <field name='name' class="headline"></field>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='ts_start' part='date' label='When'></field>
                    </column>
                    <column showif='all_day=f'>
                        <field name='ts_start' part='time' hidelabel='t'></field>
                    </column>
                    <column>
                        <field name='ts_end' part='time' hidelabel='t'></field>
                    </column>
                    <column>
                        <field name='all_day'></field>
                    </column>
                </row>
                <row>
                    <column>
                        <recurrence></recurrence>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='location'></field>
                    </column>
                </row>
                <row>
                    <column width='95px'><label>Calendar</label></column>
                    <column width='150px'>
                        <label>Plugin: Calendar_Sel</label>
                    </column>
                    <column width='95px'><label>This event is</label></column>
                    <column>
                        <field name='sharing' hidelabel='t'></field>
                    </column>
                </row>
                <row>
                    <column>
                        <all_additional></all_additional>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='notes' hidelabel='t' multiline='t'></field>
                    </column>
                </row>
                <row>
                    <column>
                        <field name='comments'></field>
                    </column>
                </row>
            </tab>

            <tab name='Activity'>
                <field name='activity'></field>
            </tab>
        </tabs>
    </column>
    <column type="sidebar">
        <header>Attendees</header>
        <row>
            <column>
                <plugin name='Members' field='attendees'></plugin>
            </column>
        </row>
        <header>References</header>
        <row>
            <column>
                <field name='customer_id' hidelabel='f'></field>
                <field name='contact_id' hidelabel='f'></field>
            </column>
        </row>
    </column>
</row>
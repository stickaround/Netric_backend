<form>
    <row>
        <column>
            <row>
                <column>
                    <header banner_image_field='banner_image_id' image_field='image_id' header_fields='full_name,job_title' subheader_fields='city,district,country' />
                </column>
            </row>
        </column>
    </row>
    <row>
        <column>
            <field name='notes' hidelabel='true' multiline='true' />
            <wall />
        </column>
        <column type="sidebar">
            <row showif="editMode=true">
                <column>
                    <fieldset name='Details'>
                        <field name='name' validator='username' />
                        <field name='full_name' />
                        <field name='job_title' />
                        <user_password/>
                    </fieldset>
                </column>
            </row>
            <row showif="editMode=true">
                <column>
                    <user_admin>
                        <fieldset name='Admin'>
                            <field name='team_id' />
                            <field name='active' />
                            <field name='groups' hidelabel='true' />
                            <field name='manager_id' />
                        </fieldset>
                    </user_admin>
                </column>
            </row>
            <row>
                <column>
                    <fieldset name='Contact'>
                        <field label='Email' name='email' />
                        <field label='Mobile' name='phone_mobile' />
                        <field label='Office' name='phone_office' />
                        <field label='Ext' name='phone_ext' />
                    </fieldset>
                </column>
            </row>
            <row showif="editMode=true">
                <column>
                    <fieldset name='Location'>
                        <field name='city' />
                        <field name='district' />
                        <field name='country' />
                    </fieldset>
                </column>
            </row>
        </column>
    </row>
</form>
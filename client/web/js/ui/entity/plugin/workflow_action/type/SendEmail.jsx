/**
 * Handle a send email type action
 *
 * All actions have a 'data' field, which is just a JSON encoded string
 * used by the backend when executing the action.
 *
 * When the ActionDetails plugin is rendered it will decode or parse the string
 * and pass it down to the type component.
 *
 * @jsx React.DOM
 */
'use strict';

var React = require('react');
var ReactDOM = require('react-dom');
var netric = require("../../../../../base");
var controller = require("../../../../../controller/controller");
var entityLoader = require('../../../../../entity/loader');
var FieldsDropDown = require("../../../FieldsDropDown.jsx");
var RecipientsInput = require("./send_email/RecipientsInput.jsx")
var Controls = require('../../../../Controls.jsx');
var TextField = Controls.TextField;
var RadioButton = Controls.RadioButton;
var RadioButtonGroup = Controls.RadioButtonGroup;
var FlatButton = Controls.FlatButton;

var emailType = {
    COMPOSE: 'compose',
    TEMPLATE: 'template'
}

/**
 * Manage action data for SendEmail
 */
var SendEmail = React.createClass({

    /**
     * Expected props
     */
    propTypes: {

        /**
         * Callback to call when a user changes any properties of the action
         */
        onChange: React.PropTypes.func,

        /**
         * Flag indicating if we are in edit mode or view mode
         */
        editMode: React.PropTypes.bool,

        /**
         * The object type this action is running against
         */
        objType: React.PropTypes.string.isRequired,

        /**
         * Data from the action - decoded JSON object
         */
        data: React.PropTypes.object
    },

    /**
     * Get the starting state of this component
     */
    getInitialState: function () {

        // We need to know the type of object we are acting on
        return {
            emailType: (this.props.data.fid) ? emailType.TEMPLATE : emailType.COMPOSE,
            templateName: null,
            emailFrom: this.props.data.from,
            showMergeField: false
        };
    },

    componentDidMount: function () {
        if (this.props.data.fid) {
            entityLoader.get('html_template', this.props.data.fid, function (entity) {
                this.setState({templateName: entity.getValue('name')});
            }.bind(this));
        }
    },

    componentDidUpdate: function () {
        this._setInputValues();
    },

    /**
     * Render action type form
     *
     * @returns {JSX}
     */
    render: function () {

        if (this.props.editMode) {

            let displayEmailCompose = null;

            // If the emailType selected is Compose, then we will display elements needed to compose an email
            if (this.state.emailType == emailType.COMPOSE) {

                var mergeFieldLabel = 'Insert Merge Field';
                var displayMergeField = null;

                if (this.state.showMergeField) {
                    mergeFieldLabel = 'Hide Merge Field Menu';

                    displayMergeField = (
                        <FieldsDropDown
                            objType={this.props.objType}
                            fieldFormat={{prepend: '<%', append: '%>'}}
                            onChange={this._handleSelectMergeField}
                            showReferencedFields={1}
                        />
                    );
                }

                // Display the input fields that will be used to compose an email
                displayEmailCompose = (
                    <div>
                        <div>
                            <div className="entity-form-field-inline-block">
                                <TextField
                                    floatingLabelText='Subject'
                                    defaultValue={this.props.data.subject}
                                    onBlur={this._handleTextInputChange.bind(this, 'subject')}
                                />
                            </div>
                            <div className="entity-form-field-inline-block">
                                <FlatButton
                                    label={mergeFieldLabel}
                                    onClick={this._handleInsertMergeField.bind(this, !this.state.showMergeField)}
                                />
                                {displayMergeField}
                            </div>
                        </div>
                        <div>
                            <TextField
                                floatingLabelText='Body'
                                ref="emailBodyInput"
                                multiLine={true}
                                defaultValue={this.props.data.body}
                                onBlur={this._handleTextInputChange.bind(this, 'body')}
                            />
                        </div>
                    </div>
                );
            } else {

                // If the emailTye selected is template, then we will display a button to let the user select an email template.

                var templateName = 'No template selected';
                var buttonLabel = 'Select';

                if (this.props.data.fid && this.state.templateName) {
                    templateName = this.state.templateName;
                    buttonLabel = 'Change';
                }

                // Display the label and button that will be used to select an email template
                displayEmailCompose = (
                    <div>
                        <div className="entity-form-field-inline-block">
                            {templateName}
                        </div>
                        <div className="entity-form-field-inline-block">
                            <FlatButton
                                label={buttonLabel + ' Email Template'}
                                onClick={this._handleSelectEmailTemplate}
                            />
                        </div>
                    </div>
                );
            }

            let recipientsDisplay = [];
            let emailRecipients = ['to', 'cc', 'bcc'];

            // We will loop thru the emailRecipients and
            emailRecipients.map(function (recipient) {
                recipientsDisplay.push(
                    <div key={recipient}>
                        <div className="entity-form-field-label">
                            {recipient.charAt(0).toUpperCase() + recipient.slice(1)}
                        </div>
                        <div>
                            <RecipientsInput
                                objType={this.props.objType}
                                selectedField={this.props.data[recipient]}
                                onCheck={this._handleCheckboxSelect.bind(this, recipient)}
                            />
                            <TextField
                                floatingLabelText='Other email addresses - separate with commas'
                                ref="toEmailOther"
                                defaultValue={this.props.data[recipient + '_other']}
                                onBlur={this._handleTextInputChange.bind(this, recipient + '_other')}
                            />
                        </div>
                    </div>
                );
            }.bind(this));

            // This will be selected as a default value in the selector dropdown
            let additionalSelectorData = [{
                payload: 'default',
                text: 'Default'
            }];

            return (
                <div className="entity-form-field">
                    <div>
                        <div className="entity-form-field-inline-block">
                            <TextField
                                floatingLabelText='From'
                                ref="fromInput"
                                defaultValue={this.props.data.from}
                            />
                        </div>
                        <div className="entity-form-field-inline-block">
                            <FieldsDropDown
                                objType={this.props.objType}
                                filterBy="subtype"
                                filterText="user"
                                fieldFormat={{prepend: '<%', append: '%>'}}
                                includeFieldManager={true}
                                selectedField={this.state.emailFrom}
                                additionalMenuData={additionalSelectorData}
                                onChange={this._handleMenuSelect}
                            />
                        </div>
                    </div>
                    {recipientsDisplay}
                    <div className="entity-form-group">
                        <RadioButtonGroup
                            name='emailType'
                            defaultSelected={this.state.emailType}
                            onChange={this._handleTypeChange}
                            inline={true}>
                            <RadioButton
                                value={emailType.COMPOSE}
                                label='Compose New Email '
                            />
                            <RadioButton
                                value={emailType.TEMPLATE}
                                label='Use Email Template'
                            />
                        </RadioButtonGroup>
                    </div>
                    {displayEmailCompose}
                </div>
            );
        } else {

            let displayData = [];

            // Loop thru props.data and display the details
            for (var field in this.props.data) {
                var value = this.props.data[field];

                if (field == 'fid' && this.state.templateName) {
                    field = 'Template Name';
                    value = this.state.templateName;
                }

                displayData.push(
                    <div key={field}>
                        <div className="entity-form-field-label">
                            {field}
                        </div>
                        <div>
                            {value}
                        </div>
                    </div>
                )
            }

            // If we are not on editMode then lets just display the send email info
            return (
                <div className="entity-form-field">
                    {displayData}
                </div>
            );
        }
    },

    /**
     * When a property changes send an event so it can be handled
     *
     * @param {string} property The name of the property that was changed
     * @param {string|int|Object} value Whatever we set the property to
     * @private
     */
    _handleDataChange: function (property, value) {
        let data = this.props.data;
        data[property] = value;
        if (this.props.onChange) {
            this.props.onChange(data);
        }
    },

    _handleRemoveDataProperty: function (property) {
        let data = this.props.data;

        // Loop thru data and find the property to be removed in the data
        for (var field in data) {

            // If we found the property to remove, then let's get out from the loop since we have already deleted the field
            if (field == property) {
                delete data[field];
                break;
            }
        }

        // Update the data with the new changes
        if (this.props.onChange) {
            this.props.onChange(data);
        }
    },

    /**
     * Callback used to handle the changing of compose email type
     *
     * @param {DOMEvent} e Reference to the DOM event being sent
     * @param {string} newSelection The new selected value
     * @private
     */
    _handleTypeChange: function (e, newSelection) {

        // If compose is selected, then we will remove the value of template id (fid)
        if (newSelection == emailType.COMPOSE) {
            this._handleRemoveDataProperty('fid');
        } else {
            this._handleRemoveDataProperty('subject');
            this._handleRemoveDataProperty('body');
        }

        this.setState({emailType: newSelection})
    },

    /**
     * Callback used to handle the changing of text inputs for send email data
     *
     * @param {string} property The name of the property that was changed
     * @param {DOMEvent} evt Reference to the DOM event being sent
     * @private
     */
    _handleTextInputChange: function (property, evt) {
        this._handleDataChange(property, evt.target.value);
    },

    /**
     * Callback used to handle the selecting of user dropdown menu
     *
     * @param {string} fieldValue The value of the fieldname that was selected
     * @private
     */
    _handleMenuSelect: function (fieldValue) {
        if (fieldValue === 'default') {
            this._handleDataChange('from', netric.getApplication().getAccount().getUser().email);
        } else {
            this._handleDataChange('from', fieldValue);
        }

        this.setState({emailFrom: fieldValue})
    },

    /**
     * Callback used to handle the selecting of field checkbox
     *
     * @param {string} property The name of the property that was changed
     * @param {string} fieldValue The value of the field that was checked
     * @param {bool} isChecked The current state of the checkbox
     * @private
     */
    _handleCheckboxSelect: function (property, fieldValue, isChecked) {
        var data = this.props.data[property];

        // if data data is not defined, then lets set it to an array variable type
        if (!data) {
            data = [];
        }

        if (isChecked) {
            data.push(fieldValue)
        } else {

            // if the fieldValue is deselected, then we need to remove that fieldValue in the data array
            for (var idx in data) {
                if (data[idx] == fieldValue) {
                    data.splice(idx, 1);
                }
            }
        }

        this._handleDataChange(property, data);
    },

    /**
     * Callback used to handle the inserting of merge field
     *
     * @param {bool} flagShowHide Flag that will determine if we will show or hide the merge field menu
     * @private
     */
    _handleInsertMergeField: function (flagShowHide) {
        this.setState({showMergeField: flagShowHide})
    },

    /**
     * Callback used to handle the selecting of merge field
     *
     * @param {string} fieldValue The value of the fieldname that was selected
     * @private
     */
    _handleSelectMergeField: function(fieldValue) {

        var body = this.refs.emailBodyInput.getValue() + fieldValue;
        this.refs.emailBodyInput.setValue(body);
        this._handleDataChange('body', body);

        this.setState({showMergeField: false})
    },

    /**
     * Callback used to handle the selecting of email template by displaying the entity browser
     *
     * @private
     */
    _handleSelectEmailTemplate: function () {

        /*
         * We require it here to avoid a circular dependency where the
         * controller requires the view and the view requires the controller
         */
        var BrowserController = require('../../../../../controller/EntityBrowserController');
        var browser = new BrowserController();
        browser.load({
            type: controller.types.DIALOG,
            title: 'Select Email Template',
            objType: 'html_template',
            onSelect: function (objType, id, name) {
                this._handleDataChange('fid', id);
                this.setState({templateName: name});
            }.bind(this)
        });
    },

    /**
     * Set intial values for the input text for send email from
     * @private
     */
    _setInputValues: function () {
        let from = this.props.data.from || null;

        if (this.refs.fromInput) {
            this.refs.fromInput.setValue(from);
        }
    }
});

module.exports = SendEmail;

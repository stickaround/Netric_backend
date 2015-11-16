/**
 * Monthly component.
 *
 * @jsx React.DOM
 */
'use strict';

var React = require('react');
var Chamel = require('chamel');
var TextField = Chamel.TextField;
var RadioButton = Chamel.RadioButton;
var DropDownMenu = Chamel.DropDownMenu;
var RadioButtonGroup = Chamel.RadioButtonGroup;

var occurenceMenu = [
    {key: '1', text: 'The First'},
    {key: '2', text: 'The Second'},
    {key: '3', text: 'The Third'},
    {key: '4', text: 'The Fourt'},
    {key: '5', text: 'The Last'},
];

var typeMonthly = '3';
var typeMonthNth = '4';


var Monthly = React.createClass({

    propTypes: {
        data: React.PropTypes.object,
        dayOfWeek: React.PropTypes.array.isRequired,
        instance: React.PropTypes.array.isRequired
    },

    getDefaultProps: function () {
        var data = {
            type: typeMonthly,
            dayOfMonth: 1,
            intervalMonth: 1,
            dayOfWeek: 1,
            intervalNth: 1,
            instance: 1
        };

        return {
            data: data
        }
    },

    getInitialState: function () {

        var data = this.props.data;

        if (data.interval && data.type == typeMonthly) {
            data.intervalMonth = data.interval;
        } else if (data.interval) {
            data.intervalNth = data.interval;
        }

        for (var idx in this.props.dayOfWeek) {
            var dayOfWeek = this.props.dayOfWeek[idx];

            if (data['day' + dayOfWeek.key] && data['day' + dayOfWeek.key] == 't') {
                data.nthDayOfWeek = dayOfWeek.key;
            }
        }

        // Return the initial state
        return {
            data: data
        };
    },

    componentDidMount: function () {
        this._setInputValues();
    },

    componentDidUpdate: function () {
        this._setInputValues();
    },

    render: function () {
        var displayType = null;

        if (this.state.data.type == typeMonthly) {
            displayType = (
                <div className="row">
                    <div className="col-small-5">
                        <label>Day</label>
                        <TextField
                            className='recurrence-input'
                            ref='inputDayOfMonth'/>
                        <label>of every</label>
                        <TextField
                            className='recurrence-input'
                            ref='inputIntervalMonth'/>
                        <label>Month(s)</label>
                    </div>
                </div>
            );
        } else {
            displayType = (
                <div className="row">
                    <div className="col-small-6">
                        <DropDownMenu
                            onChange={this._handleDropDownChange.bind(this, 'instance')}
                            selectedIndex={this.props.data.instance - 1}
                            menuItems={this.props.instance}/>
                        <DropDownMenu
                            onChange={this._handleDropDownChange.bind(this, 'dayOfWeek')}
                            selectedIndex={this.props.data.dayOfWeek - 1}
                            menuItems={this.props.dayOfWeek}/>
                    </div>
                    <div>
                        <label>of every</label>
                        <TextField
                            className='recurrence-input'
                            ref='inputIntervalNth'/>
                        <label>Month(s)</label>
                    </div>
                </div>
            );
        }

        return (
            <div>
                <div>
                    <RadioButtonGroup
                        className='row'
                        name='inputMonthly'
                        ref='radioType'
                        defaultSelected={this.state.data.type}
                        onChange={this._handleTypeChange}>
                        <RadioButton
                            className='col-small-2'
                            value={typeMonthly}
                            label='Monthly'/>

                        <RadioButton
                            className='col-small-3'
                            value={typeMonthNth}
                            label='Month-nth'/>
                    </RadioButtonGroup>
                </div>
                {displayType}
            </div>
        );
    },

    /**
     * Callback used to handle the changing of month pattern type
     *
     * @param {DOMEvent} e              Reference to the DOM event being sent
     * @param {string} newSelection     The new selected value
     * @private
     */
    _handleTypeChange: function (e, newSelection) {
        var data = this.state.data;
        data.type = newSelection;

        this._setStateData(data);
    },

    /**
     * Callback used to handle the changing of dropdown menus
     *
     * @param {string} type         Type of dropdown menu that was changed
     * @param {DOMEvent} e          Reference to the DOM event being sent
     * @param {int} key             The index of the menu clicked
     * @param {array} menuItem      The object value of the menu clicked
     * @private
     */
    _handleDropDownChange: function (type, e, key, menuItem) {
        var data = this.state.data;
        data[type] = menuItem.key;

        this._setStateData(data);
    },

    /**
     * Saves the data into the state
     *
     * @param {object} data     Collection of data that will be stored in the state
     * @private
     */
    _setStateData: function (data) {

        /*
         * Lets save the input values in the state to be retrieved later.
         * If the user changes the type of monthly recurrence, the state values are retrieved
         */
        if (this.refs.inputDayOfMonth) {
            data.dayOfMonth = this.refs.inputDayOfMonth.getValue();
            data.intervalMonth = this.refs.inputIntervalMonth.getValue();
        } else if (this.refs.inputIntervalNth) {
            data.intervalNth = this.refs.inputIntervalNth.getValue();
        }

        this.setState({data: data});
    },

    /**
     * Set the values of the input boxes
     *
     * @private
     */
    _setInputValues: function () {
        if (this.refs.inputDayOfMonth) {
            this.refs.inputDayOfMonth.setValue(this.state.data.dayOfMonth);
            this.refs.inputIntervalMonth.setValue(this.state.data.intervalMonth);
        } else if (this.refs.inputIntervalNth) {
            this.refs.inputIntervalNth.setValue(this.state.data.intervalNth);
        }
    },

    /**
     * Gets the recurrence pattern data set by the user
     *
     * @return {object}
     * @public
     */
    getData: function (isState) {
        var data = {};

        if (isState) {
            data.type = this.state.data.type;

            if (data.type == typeMonthly) {
                data.dayOfMonth = this.refs.inputDayOfMonth.getValue();
                data.interval = this.refs.inputIntervalMonth.getValue();
            } else {
                data.interval = this.refs.inputIntervalNth.getValue();
                data.instance = this.state.data.instance;
                data['day' + this.state.data.dayOfWeek] = 't';
            }
        } else {
            data = this.state.data;
        }

        return data;
    }
});

module.exports = Monthly;

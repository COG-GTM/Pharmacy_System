
import './bootstrap';
import 'laravel-datatables-vite';
import select2 from 'select2';

$(document).ready(function () {

    $(".select2").select2({
        tags: true,
        theme: 'bootstrap-5',
    });
    $(".select2").on("select2:select", function (evt) {
        var element = evt.params.data.element;
        const inputContainer = $('#input-container');
        var $element = $(element);
        $element.detach();
        $(this).append($element);
        $(this).trigger("change");

        inputContainer.empty();


    const numSelected = ($(this).find('option:selected')).length;
    const numInputs = 0;
    if (numInputs > numSelected) {
        inputContainer.find('input:gt(' + (numSelected - 1) + ')').remove();
    }
    for (let i = numInputs + 1; i <= numSelected; i++) {
        const input = $('<input id="quantity" type="text" name="quantity[]" class="form-control" placeholder="Multiple Input in Input Group">');
        inputContainer.append(input);
    }

    });

    $("#edit_medicine").on("select2:select select2:unselect", function () {
        const edit = $('#editQuantity');
        const quantities = {};
        edit.find('input').each(function () {
            quantities[$(this).attr('data-medicine-id')] = $(this).val();
        });
        edit.empty();
        $(this).find('option:selected').each(function () {
            edit.append($('<input type="text" name="quantity[]" class="form-control" placeholder="Quantity">')
                .attr('data-medicine-id', this.value)
                .val(quantities[this.value] ?? ''));
        });
    });
});

    select2();

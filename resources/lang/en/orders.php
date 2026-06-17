<?php

return [
    // Status labels
    'status_pending' => 'Pending',
    'status_in_progress' => 'In Progress',
    'status_try_on' => 'Doctor Try-On',
    'status_resend_wrong_impression' => 'Resend Wrong Impression',
    'status_completed' => 'Completed',
    'status_delivered' => 'Delivered',

    // Messages
    'retrieved_successfully' => 'Orders retrieved successfully',
    'details_retrieved_successfully' => 'Order details retrieved successfully',
    'resubmission_marked_successfully' => 'Order was marked for doctor resubmission successfully',
    'resubmission_not_allowed_for_status' => 'This order status cannot be marked for resubmission',
    'created_successfully' => 'Order created successfully',
    'delivery_employees_retrieved' => 'Delivery employees retrieved successfully',
    'delivery_assigned_successfully' => 'Delivery employee assigned successfully',
    'delivery_user_invalid' => 'Selected delivery employee is not available for this lab',
    'delivery_already_assigned' => 'This order already has an active delivery assignment',
    'status_updated_successfully' => 'Order status updated successfully',
    'department_route_set_successfully' => 'Order department route set successfully',
    'route_already_locked' => 'Cannot modify department route once tasks have been created',
    'department_not_in_lab' => 'Selected department does not belong to this lab',
    'no_orders_for_route' => 'No orders in your lab are eligible for department routing',

    'delivery_status_updated' => 'Delivery status updated successfully',
    'delivery_status_transition_invalid' => 'Cannot change delivery status to the requested status',

    // Direction labels
    'direction_to_lab' => 'To Lab',
    'direction_to_doctor' => 'To Doctor',
];

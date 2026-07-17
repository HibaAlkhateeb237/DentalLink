<?php

return [
    // Status labels
    'status_pending' => 'Pending',
    'status_in_progress' => 'In Progress',
    'status_try_on' => 'Doctor Try-On',
    'status_resend_wrong_impression' => 'Resend Wrong Impression',
    'status_completed' => 'Completed',
    'status_delivered' => 'Delivered',

    // Notifications
    'order_processing_started_notification' => 'Order #:serial_number is now being '.' :status',
    'order_completed_notification' => 'Order #:serial_number has been completed',
    'order_completed_body' => 'Your order for patient "#patient_name" is now complete.',
    'retrieved_successfully' => 'Orders retrieved successfully',
    'details_retrieved_successfully' => 'Order details retrieved successfully',
    'resubmission_marked_successfully' => 'Order was marked for doctor resubmission successfully',
    'resubmission_not_allowed_for_status' => 'This order status cannot be marked for resubmission',
    'created_successfully' => 'Order created successfully',
    'delivery_employees_retrieved' => 'Delivery employees retrieved successfully',
    'delivery_assigned_successfully' => 'Delivery employee assigned successfully',
    'delivery_user_invalid' => 'Selected delivery employee is not available for this lab',
    'delivery_already_assigned' => 'This order already has an active delivery assignment',
    'delivery_delivered_notification' => 'تم تسليم الطلب إلى الطبيب',
    'delivery_delivered_body' => 'تم تسليم الطلب رقم #:serial_number للمريض ":patient_name" إلى الطبيب.',
    'status_updated_successfully' => 'Order status updated successfully',
    'order_locked' => 'تم قفل الطلب بنجاح',
    'order_unlocked' => 'تم فتح قفل الطلب بنجاح',
    'order_locked_by_another' => 'هذا الطلب مقفل حالياً بواسطة :name',
    'order_already_locked' => 'هذا الطلب مقفل بالفعل',
    'department_route_set_successfully' => 'Order department route set successfully',
    'route_already_locked' => 'Cannot modify department route once tasks have been created',
    'department_not_in_lab' => 'Selected department does not belong to this lab',
    'no_orders_for_route' => 'No orders in your lab are eligible for department routing',

    'delivery_status_updated' => 'Delivery status updated successfully',
    'delivery_status_transition_invalid' => 'Cannot change delivery status to the requested status',

    // Direction labels
    'direction_to_lab' => 'To Lab',
    'direction_to_doctor' => 'To Doctor',

    'qr_already_printed' => 'تم طباعة رمز الاستجابة السريعة بالفعل ولا يمكن طباعته مرة أخرى',

    'status_finalized' => 'تم اعتماد حالة الطلب بالفعل ولا يمكن تحديثها مجدداً',
];

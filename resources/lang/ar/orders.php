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

    // Delivery tracking
    'tracking_started' => 'تم بدء رحلة التوصيل بنجاح',
    'tracking_location_updated' => 'تم تحديث الموقع بنجاح',
    'tracking_ended' => 'تم إنهاء رحلة التوصيل بنجاح',
    'tracking_task_ids_required' => 'حقل مهام التوصيل مطلوب',
    'tracking_task_ids_invalid' => 'يجب أن تكون مهام التوصيل مصفوفة',
    'tracking_task_ids_min_one' => 'يجب تحديد مهمة توصيل واحدة على الأقل',
    'tracking_task_not_found' => 'واحدة أو أكثر من مهام التوصيل غير موجودة أو غير مسندة إليك',
    'tracking_tasks_same_doctor' => 'يجب أن تنتمي جميع مهام التوصيل إلى نفس الطبيب',
    'tracking_invalid_transition' => 'لا يمكن تغيير حالة التتبع للطلب رقم #:order_id من ":from" إلى ":to". المسموح: :allowed',
    'tracking_location_after_terminal' => 'لا يمكن تحديث الموقع للطلب رقم #:order_id لأن الرحلة بحالة ":status".',

    // Direction labels
    'direction_to_lab' => 'To Lab',
    'direction_to_doctor' => 'To Doctor',

    'qr_already_printed' => 'تم طباعة رمز الاستجابة السريعة بالفعل ولا يمكن طباعته مرة أخرى',

    'status_finalized' => 'تم اعتماد حالة الطلب بالفعل ولا يمكن تحديثها مجدداً',

    // Print status notifications
    'print_status_notification' => 'تحديث حالة الطباعة',
    'print_status_new_print_title' => 'طباعة جديدة جاهزة',
    'print_status_new_print_body' => 'تم تحضير طباعة جديدة للطلب رقم #:serial_number. يرجى إرسال عامل التوصيل لاستلامها.',
    'print_status_trial_title' => 'تم تجربة الطباعة',
    'print_status_trial_body' => 'تم تجربة الطباعة للطلب رقم #:serial_number. يرجى استلامها.',
    'print_status_notified_successfully' => 'تم إرسال إشعار حالة الطباعة بنجاح',
];

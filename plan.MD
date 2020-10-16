## The script needs to be receive the following payload :

```json
{
    "name": "{{ticket.ticket_field_27941965}}",
    "email": "{{ticket.requester.email}}",
    "phone": "{{ticket.ticket_field_25309499}}",
    "member_id": "{{ticket.ticket_field_25403025}}",
    "membership": "{{ticket.ticket_field_25739049}}",
    "amount": "{{ticket.ticket_field_26268975}}",
    "zendesk_id": "{{ticket.id}}",
    "dispatch_date": "{{ticket.ticket_field_26163889}}"
}
```

# Logic gates

### 1) Membership type filter

if the `"membership"` type in the JSON is one of the following :

-   `"an_post_employee"`
-   `"an_post_family_member"`
-   `"garda_medical_aid"`
-   `"pomas"`

Then the client should be exempt :

-   `"is_exempt = true"`

### 2) Membership status filter

if the client is on the new monthly plan :

-   We charge delivery fees if the order amount < €30

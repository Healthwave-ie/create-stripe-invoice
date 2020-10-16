const express = require("express");
const app = express();
const port = 3000;

app.use(express.json());

const stripe = require("stripe")(process.env.STRIPE_SECRET);

app.post("/", async (req, res) => {
    const { name, email, amount, invoiceItemDescription } = req.body;
    stripe.customers
        .create({
            email,
            name,
            description: "customer created using Node on a Lambda",
        })
        .then((customer) => {
            console.log("customer created");
            return stripe.invoiceItems
                .create({
                    customer: customer.id,
                    amount,
                    currency: "eur",
                    description: invoiceItemDescription,
                })
                .then((invoiceItem) => {
                    return stripe.invoices.create({
                        customer: invoiceItem.customer,
                    });
                })
                .then((invoice) => {
                    res.send(invoice.id);
                })
                .catch((err) => {
                    console.error(err);
                });
        });
});

module.exports = app;

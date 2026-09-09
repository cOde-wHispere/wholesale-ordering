import csv

products = [
    # Groceries
    ("Premium Rice 25kg", "Premium long grain rice suitable for homes, restaurants and retailers.", 850, 720, 5, 5, "Gambia Foods", "Groceries", "rice-25kg.jpg"),
    ("Premium Rice 10kg", "Quality long grain rice for everyday cooking.", 380, 320, 10, 5, "Gambia Foods", "Groceries", "rice-10kg.jpg"),
    ("Parboiled Rice 25kg", "High quality parboiled rice.", 900, 760, 5, 5, "WestAfrica Foods", "Groceries", "parboiled-rice.jpg"),
    ("Cooking Oil 5L", "Refined vegetable cooking oil.", 420, 350, 10, 5, "SunGold", "Groceries", "cooking-oil-5l.jpg"),
    ("Cooking Oil 1L", "Refined vegetable cooking oil for household use.", 95, 78, 20, 10, "SunGold", "Groceries", "cooking-oil-1l.jpg"),
    ("Sugar 1kg", "Premium white granulated sugar.", 75, 60, 20, 10, "SweetLife", "Groceries", "sugar-1kg.jpg"),
    ("Sugar 5kg", "Premium white granulated sugar in a 5kg package.", 340, 285, 10, 5, "SweetLife", "Groceries", "sugar-5kg.jpg"),
    ("Wheat Flour 1kg", "Fine wheat flour suitable for baking.", 80, 65, 20, 10, "BakePro", "Groceries", "wheat-flour.jpg"),
    ("Corn Flour 1kg", "Fine corn flour for cooking and baking.", 85, 68, 20, 10, "Gambia Foods", "Groceries", "corn-flour.jpg"),
    ("Spaghetti 500g", "Quality durum wheat spaghetti.", 55, 42, 24, 12, "PastaHouse", "Groceries", "spaghetti.jpg"),

    # Beverages
    ("Bottled Water 500ml", "Pure bottled drinking water.", 15, 11, 48, 24, "Gambia Pure", "Beverages", "water-500ml.jpg"),
    ("Bottled Water 1.5L", "Pure bottled drinking water.", 25, 19, 24, 12, "Gambia Pure", "Beverages", "water-1.5l.jpg"),
    ("Mango Juice 1L", "Refreshing mango fruit juice.", 85, 70, 12, 6, "Tropical", "Beverages", "mango-juice.jpg"),
    ("Orange Juice 1L", "Refreshing orange fruit juice.", 85, 70, 12, 6, "Tropical", "Beverages", "orange-juice.jpg"),
    ("Soft Drink 500ml", "Refreshing carbonated soft drink.", 30, 24, 24, 12, "FizzUp", "Beverages", "soft-drink.jpg"),

    # Household
    ("Laundry Powder 2kg", "Powerful laundry detergent for everyday washing.", 180, 145, 12, 6, "CleanPro", "Household", "laundry-2kg.jpg"),
    ("Laundry Powder 1kg", "Effective detergent for household laundry.", 95, 76, 20, 10, "CleanPro", "Household", "laundry-1kg.jpg"),
    ("Dishwashing Liquid 750ml", "Liquid detergent for cleaning dishes.", 70, 55, 20, 10, "CleanPro", "Household", "dishwashing.jpg"),
    ("Toilet Cleaner 500ml", "Powerful toilet cleaning solution.", 65, 50, 20, 10, "CleanPro", "Household", "toilet-cleaner.jpg"),
    ("Floor Cleaner 1L", "Multi-purpose floor cleaning solution.", 95, 75, 12, 6, "FreshHome", "Household", "floor-cleaner.jpg"),

    # Personal Care
    ("Bath Soap 100g", "Gentle soap for everyday bathing.", 35, 27, 24, 12, "FreshCare", "Personal Care", "bath-soap.jpg"),
    ("Shampoo 400ml", "Daily care shampoo for healthy hair.", 140, 110, 12, 6, "FreshCare", "Personal Care", "shampoo.jpg"),
    ("Toothpaste 100ml", "Fluoride toothpaste for daily oral care.", 75, 60, 24, 12, "SmilePlus", "Personal Care", "toothpaste.jpg"),
    ("Body Lotion 400ml", "Moisturizing body lotion.", 150, 120, 12, 6, "SoftSkin", "Personal Care", "body-lotion.jpg"),
    ("Deodorant 150ml", "Long-lasting body deodorant.", 120, 95, 12, 6, "FreshCare", "Personal Care", "deodorant.jpg"),
]

# Automatically expand the initial products to 100 test products
base_products = products.copy()

counter = 1

while len(products) < 100:
    original = base_products[(len(products) - len(base_products)) % len(base_products)]

    name, description, regular, wholesale, minimum, step, brand, category, image = original

    products.append((
        f"{name} Test {len(products) + 1}",
        description,
        regular + counter,
        wholesale + counter,
        minimum,
        step,
        brand,
        category,
        image
    ))

    counter += 1

with open("products.csv", "w", newline="", encoding="utf-8") as file:
    writer = csv.writer(file)

    writer.writerow([
        "product_name",
        "description",
        "regular_price",
        "wholesale_price",
        "wholesale_minimum_quantity",
        "quantity_step",
        "brand",
        "category",
        "image"
    ])

    writer.writerows(products)

print(f"Created products.csv with {len(products)} products.")
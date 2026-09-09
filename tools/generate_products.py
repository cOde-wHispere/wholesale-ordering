import csv
from pathlib import Path


BASE_PRODUCTS = [
    # Groceries
    (
        "Premium Rice 25kg",
        "Premium long grain rice suitable for homes, restaurants and retailers.",
        850,
        720,
        5,
        5,
        "Gambia Foods",
        "Groceries",
        "rice-25kg.jpg",
    ),
    (
        "Premium Rice 10kg",
        "Quality long grain rice for everyday cooking.",
        380,
        320,
        10,
        5,
        "Gambia Foods",
        "Groceries",
        "rice-10kg.jpg",
    ),
    (
        "Parboiled Rice 25kg",
        "High quality parboiled rice.",
        900,
        760,
        5,
        5,
        "WestAfrica Foods",
        "Groceries",
        "parboiled-rice.jpg",
    ),
    (
        "Cooking Oil 5L",
        "Refined vegetable cooking oil.",
        420,
        350,
        10,
        5,
        "SunGold",
        "Groceries",
        "cooking-oil-5l.jpg",
    ),
    (
        "Cooking Oil 1L",
        "Refined vegetable cooking oil for household use.",
        95,
        78,
        20,
        10,
        "SunGold",
        "Groceries",
        "cooking-oil-1l.jpg",
    ),
    (
        "Sugar 1kg",
        "Premium white granulated sugar.",
        75,
        60,
        20,
        10,
        "SweetLife",
        "Groceries",
        "sugar-1kg.jpg",
    ),
    (
        "Sugar 5kg",
        "Premium white granulated sugar in a 5kg package.",
        340,
        285,
        10,
        5,
        "SweetLife",
        "Groceries",
        "sugar-5kg.jpg",
    ),
    (
        "Wheat Flour 1kg",
        "Fine wheat flour suitable for baking.",
        80,
        65,
        20,
        10,
        "BakePro",
        "Groceries",
        "wheat-flour.jpg",
    ),
    (
        "Corn Flour 1kg",
        "Fine corn flour for cooking and baking.",
        85,
        68,
        20,
        10,
        "Gambia Foods",
        "Groceries",
        "corn-flour.jpg",
    ),
    (
        "Spaghetti 500g",
        "Quality durum wheat spaghetti.",
        55,
        42,
        24,
        12,
        "PastaHouse",
        "Groceries",
        "spaghetti.jpg",
    ),

    # Beverages
    (
        "Bottled Water 500ml",
        "Pure bottled drinking water.",
        15,
        11,
        48,
        24,
        "Gambia Pure",
        "Beverages",
        "water-500ml.jpg",
    ),
    (
        "Bottled Water 1.5L",
        "Pure bottled drinking water.",
        25,
        19,
        24,
        12,
        "Gambia Pure",
        "Beverages",
        "water-1.5l.jpg",
    ),
    (
        "Mango Juice 1L",
        "Refreshing mango fruit juice.",
        85,
        70,
        12,
        6,
        "Tropical",
        "Beverages",
        "mango-juice.jpg",
    ),
    (
        "Orange Juice 1L",
        "Refreshing orange fruit juice.",
        85,
        70,
        12,
        6,
        "Tropical",
        "Beverages",
        "orange-juice.jpg",
    ),
    (
        "Soft Drink 500ml",
        "Refreshing carbonated soft drink.",
        30,
        24,
        24,
        12,
        "FizzUp",
        "Beverages",
        "soft-drink.jpg",
    ),

    # Household
    (
        "Laundry Powder 2kg",
        "Powerful laundry detergent for everyday washing.",
        180,
        145,
        12,
        6,
        "CleanPro",
        "Household",
        "laundry-2kg.jpg",
    ),
    (
        "Laundry Powder 1kg",
        "Effective detergent for household laundry.",
        95,
        76,
        20,
        10,
        "CleanPro",
        "Household",
        "laundry-1kg.jpg",
    ),
    (
        "Dishwashing Liquid 750ml",
        "Liquid detergent for cleaning dishes.",
        70,
        55,
        20,
        10,
        "CleanPro",
        "Household",
        "dishwashing.jpg",
    ),
    (
        "Toilet Cleaner 500ml",
        "Powerful toilet cleaning solution.",
        65,
        50,
        20,
        10,
        "CleanPro",
        "Household",
        "toilet-cleaner.jpg",
    ),
    (
        "Floor Cleaner 1L",
        "Multi-purpose floor cleaning solution.",
        95,
        75,
        12,
        6,
        "FreshHome",
        "Household",
        "floor-cleaner.jpg",
    ),

    # Personal Care
    (
        "Bath Soap 100g",
        "Gentle soap for everyday bathing.",
        35,
        27,
        24,
        12,
        "FreshCare",
        "Personal Care",
        "bath-soap.jpg",
    ),
    (
        "Shampoo 400ml",
        "Daily care shampoo for healthy hair.",
        140,
        110,
        12,
        6,
        "FreshCare",
        "Personal Care",
        "shampoo.jpg",
    ),
    (
        "Toothpaste 100ml",
        "Fluoride toothpaste for daily oral care.",
        75,
        60,
        24,
        12,
        "SmilePlus",
        "Personal Care",
        "toothpaste.jpg",
    ),
    (
        "Body Lotion 400ml",
        "Moisturizing body lotion.",
        150,
        120,
        12,
        6,
        "SoftSkin",
        "Personal Care",
        "body-lotion.jpg",
    ),
    (
        "Deodorant 150ml",
        "Long-lasting body deodorant.",
        120,
        95,
        12,
        6,
        "FreshCare",
        "Personal Care",
        "deodorant.jpg",
    ),
]


OUTPUT_FILE = Path(__file__).resolve().parent.parent / "data" / "products.csv"

TARGET_PRODUCT_COUNT = 100


def validate_product(product):
    (
        name,
        description,
        regular_price,
        wholesale_price,
        minimum_quantity,
        quantity_step,
        brand,
        category,
        image,
    ) = product

    if not name.strip():
        raise ValueError("Product name cannot be empty.")

    if not description.strip():
        raise ValueError(f"Description cannot be empty: {name}")

    if float(regular_price) < 0:
        raise ValueError(f"Regular price cannot be negative: {name}")

    if float(wholesale_price) < 0:
        raise ValueError(f"Wholesale price cannot be negative: {name}")

    if float(wholesale_price) > float(regular_price):
        raise ValueError(
            f"Wholesale price cannot exceed regular price: {name}"
        )

    if float(minimum_quantity) < 1:
        raise ValueError(
            f"Minimum quantity must be at least 1: {name}"
        )

    if float(quantity_step) <= 0:
        raise ValueError(
            f"Quantity step must be greater than 0: {name}"
        )

    if not category.strip():
        raise ValueError(f"Category cannot be empty: {name}")

    if not brand.strip():
        raise ValueError(f"Brand cannot be empty: {name}")


def build_products():
    products = []

    for product in BASE_PRODUCTS:
        validate_product(product)

    for index, product in enumerate(BASE_PRODUCTS, start=1):
        (
            name,
            description,
            regular_price,
            wholesale_price,
            minimum_quantity,
            quantity_step,
            brand,
            category,
            image,
        ) = product

        products.append(
            (
                f"wo-seed-{index:03d}",
                name,
                description,
                regular_price,
                wholesale_price,
                minimum_quantity,
                quantity_step,
                brand,
                category,
                image,
            )
        )

    counter = 1

    while len(products) < TARGET_PRODUCT_COUNT:
        source_index = (
            len(products) - len(BASE_PRODUCTS)
        ) % len(BASE_PRODUCTS)

        original = BASE_PRODUCTS[source_index]

        (
            name,
            description,
            regular_price,
            wholesale_price,
            minimum_quantity,
            quantity_step,
            brand,
            category,
            image,
        ) = original

        generated_number = len(products) + 1

        generated_product = (
            f"wo-seed-{generated_number:03d}",
            f"{name} Test {generated_number}",
            description,
            regular_price + counter,
            wholesale_price + counter,
            minimum_quantity,
            quantity_step,
            brand,
            category,
            image,
        )

        validate_product(generated_product[1:])

        products.append(generated_product)

        counter += 1

    return products


def write_csv(products):
    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)

    with OUTPUT_FILE.open(
        "w",
        newline="",
        encoding="utf-8",
    ) as file:
        writer = csv.writer(file)

        writer.writerow(
            [
                "seed_id",
                "product_name",
                "description",
                "regular_price",
                "wholesale_price",
                "wholesale_minimum_quantity",
                "quantity_step",
                "brand",
                "category",
                "image",
            ]
        )

        writer.writerows(products)


def main():
    products = build_products()

    if len(products) != TARGET_PRODUCT_COUNT:
        raise RuntimeError(
            f"Expected {TARGET_PRODUCT_COUNT} products, "
            f"generated {len(products)}."
        )

    seed_ids = [product[0] for product in products]

    if len(seed_ids) != len(set(seed_ids)):
        raise RuntimeError("Duplicate seed_id detected.")

    write_csv(products)

    print(
        f"Created {OUTPUT_FILE} with "
        f"{len(products)} products."
    )


if __name__ == "__main__":
    main()